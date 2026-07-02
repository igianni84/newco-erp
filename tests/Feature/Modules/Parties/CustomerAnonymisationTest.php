<?php

use App\Modules\Module;
use App\Modules\Parties\Actions\AnonymiseCustomer;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerClosed;
use App\Modules\Parties\Exceptions\AnonymisationBlockedByComplianceHold;
use App\Modules\Parties\Models\Address;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\AnonymisedPlaceholders;
use App\Platform\Audit\AuditRecord;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the GDPR right-to-erasure core — the {@see AnonymiseCustomer} action's gate + PII overwrite-in-place +
 * `anonymised_at` stamp (parties-anonymisation task 3.2, design D1/D2/D4; party-registry — Requirements: Customer
 * Anonymisation (Right-to-Erasure), Anonymisation Hold Precedence, Customer Address; AC-K-J-9 / AC-K-J-9a /
 * AC-K-FSM-16 / BR-K-Customer-2; canon MVP-DEC-015 — ADR
 * decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md). It drives the REAL Action and asserts:
 *   - it overwrites the Customer PII (`name`/`email`/`phone`/`date_of_birth`) + every scoped Address's personal
 *     fields with the deterministic id-derived placeholders ({@see AnonymisedPlaceholders}),
 *     stamps `anonymised_at`, and PRESERVES the Profile/Address rows (overwrite-in-place, never deleted);
 *   - it is ORTHOGONAL to the status FSM — a `closed` (or `active`) Customer keeps its status, and NO status event
 *     ({@see CustomerClosed}) is recorded (an event-count delta of 0 — this task records no event; the PII-free
 *     `CustomerAnonymised` event is task 3.4);
 *   - the Hold-precedence gate blocks IFF an active `compliance` Hold covers the Customer (canon MVP-DEC-015 —
 *     compliance-only, count-independent), throwing {@see AnonymisationBlockedByComplianceHold} and leaving the
 *     Customer entirely un-anonymised; a non-`compliance` Hold does NOT block (the full per-type precedence matrix
 *     is task 4.1);
 *   - placeholders are id-keyed so two anonymised Customers keep distinct emails (the globally-unique-email
 *     invariant holds), and a re-run is an idempotent no-op.
 *
 * Fixtures are stood up through factories (the sibling CustomerClosureAndAccountStatusTest convention — factories
 * bypass the Actions and record NO event, so every counted event is one the Action recorded; a `compliance` Hold
 * is placed via HoldFactory to isolate the gate read from the PlaceHold status-coupling machinery — the
 * DatabaseComplianceStatusReader reads `parties_holds` directly). RefreshDatabase per the directory convention;
 * the Action opens its OWN DB::transaction. Assertions read persisted rows (re-fetched) and events BY NAME — never
 * a byte-compare of stored jsonb — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('overwrites Customer PII + Address personal fields, stamps anonymised_at, preserves rows and status, records no event', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => Club::factory()->create()->id,
    ]);
    $addressA = Address::factory()->create(['customer_id' => $customer->id]);
    $addressB = Address::factory()->forCompany()->create(['customer_id' => $customer->id]);

    // The company-billing Address genuinely carries company data before erasure — so nulling it below is meaningful.
    expect($addressB->company_name)->not->toBeNull();
    // Factories record no event — the delta below is honest.
    expect(DomainEvent::query()->count())->toBe(0);

    $returned = app(AnonymiseCustomer::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);

    // (a) PII overwritten with the deterministic id-derived placeholders; phone/dob erased to NULL. (b) anonymised_at
    // stamped. Orthogonal to status — the write touched no status. The returned model reflects the same overwrite.
    expect($fresh->email)->toBe("anonymised+{$customer->id}@anonymised.invalid")
        ->and($fresh->name)->toBe("Anonymised Customer {$customer->id}")
        ->and($fresh->phone)->toBeNull()
        ->and($fresh->date_of_birth)->toBeNull()
        ->and($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->status)->toBe(CustomerStatus::Active)
        ->and($returned->email)->toBe("anonymised+{$customer->id}@anonymised.invalid");

    // (a) every scoped Address's personal fields overwritten; the two nullable/company fields nulled.
    foreach ([$addressA->id, $addressB->id] as $addressId) {
        $freshAddress = Address::findOrFail($addressId);
        expect($freshAddress->line1)->toBe('Anonymised')
            ->and($freshAddress->locality)->toBe('Anonymised')
            ->and($freshAddress->postal_code)->toBe('Anonymised')
            ->and($freshAddress->country_code)->toBe('ZZ')
            ->and($freshAddress->line2)->toBeNull()
            ->and($freshAddress->region)->toBeNull()
            ->and($freshAddress->company_name)->toBeNull()
            ->and($freshAddress->vat_id)->toBeNull();
    }

    // Rows PRESERVED (never deleted): both Addresses + the Profile still exist keyed to the Customer.
    expect(Address::query()->where('customer_id', $customer->id)->count())->toBe(2)
        ->and(Profile::findOrFail($profile->id)->customer_id)->toBe($customer->id);

    // No domain event recorded — anonymisation writes no status event; the CustomerAnonymised event lands in task 3.4.
    expect(DomainEvent::query()->count())->toBe(0);
});

it('is blocked by an active compliance Hold — throws and leaves the Customer entirely un-anonymised', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $address = Address::factory()->create(['customer_id' => $customer->id]);

    $originalEmail = $customer->email;
    $originalLine1 = $address->line1;

    // An active `compliance` Hold on the Customer scope — the sole blocking type (canon MVP-DEC-015).
    Hold::factory()->create([
        'hold_type' => HoldType::Compliance,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    expect(fn () => app(AnonymiseCustomer::class)->handle($customer->id))
        ->toThrow(AnonymisationBlockedByComplianceHold::class);

    // Nothing changed: PII intact, anonymised_at NULL, Address intact.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->email)->toBe($originalEmail)
        ->and($fresh->anonymised_at)->toBeNull()
        ->and(Address::findOrFail($address->id)->line1)->toBe($originalLine1);
});

it('proceeds when the only active Hold is non-compliance (e.g. payment) — the full precedence matrix is task 4.1', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Hold::factory()->create([
        'hold_type' => HoldType::Payment,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    app(AnonymiseCustomer::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->email)->toBe("anonymised+{$customer->id}@anonymised.invalid");
});

it('is an idempotent no-op on an already-anonymised Customer', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    app(AnonymiseCustomer::class)->handle($customer->id);
    $afterFirst = Customer::findOrFail($customer->id);

    // A second call changes nothing and records nothing.
    app(AnonymiseCustomer::class)->handle($customer->id);
    $fresh = Customer::findOrFail($customer->id);

    expect($fresh->email)->toBe($afterFirst->email)
        ->and($fresh->anonymised_at)->toEqual($afterFirst->anonymised_at);
    expect(DomainEvent::query()->count())->toBe(0);
});

it('anonymises two Customers with distinct id-derived emails (the globally-unique-email invariant holds)', function () {
    $a = Customer::factory()->create();
    $b = Customer::factory()->create();

    app(AnonymiseCustomer::class)->handle($a->id);
    app(AnonymiseCustomer::class)->handle($b->id);

    $freshA = Customer::findOrFail($a->id);
    $freshB = Customer::findOrFail($b->id);

    expect($freshA->email)->toBe("anonymised+{$a->id}@anonymised.invalid")
        ->and($freshB->email)->toBe("anonymised+{$b->id}@anonymised.invalid");
    expect($freshA->email)->not->toBe($freshB->email);
});

it('anonymises a closed Customer without changing its status or recording a status event (orthogonality)', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Closed]);

    app(AnonymiseCustomer::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->status)->toBe(CustomerStatus::Closed)
        ->and($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->email)->toBe("anonymised+{$customer->id}@anonymised.invalid");

    // Orthogonal to the status FSM: no CustomerClosed (or any) event recorded.
    expect(DomainEvent::query()->where('name', CustomerClosed::NAME)->count())->toBe(0);
    expect(DomainEvent::query()->count())->toBe(0);
});

it('redacts the Customer\'s own audit-record PII snapshots on anonymisation, preserving the immutable row and other entities', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $other = Customer::factory()->create(['status' => CustomerStatus::Active]);

    // Module K writes NO audit snapshots today (task-3.3 investigation: no AuditRecorder caller under
    // app/Modules/Parties), so CONSTRUCT a PII-bearing Customer audit row to prove the redaction path — as a
    // future Parties audit-writer would record it (entity_type 'Customer', the § 15.1 Customer envelope value).
    // RefreshDatabase already wraps the test in a transaction, satisfying the recorder's no-dual-write guard.
    $auditId = app(AuditRecorder::class)->record(
        action: 'parties.customer.example',
        module: Module::Parties->value,
        actorRole: ActorRole::NewcoOps,
        actorId: null,
        entityType: 'Customer',
        entityId: (string) $customer->id,
        before: ['email' => $customer->email, 'name' => $customer->name],
        after: ['email' => $customer->email, 'name' => $customer->name],
        authorizationBasis: 'operator_console',
    )->id;

    // A DIFFERENT Customer's audit row must survive untouched — the redaction is keyed to (entity_type, entity_id).
    $otherAuditId = app(AuditRecorder::class)->record(
        action: 'parties.customer.example',
        module: Module::Parties->value,
        actorRole: ActorRole::NewcoOps,
        actorId: null,
        entityType: 'Customer',
        entityId: (string) $other->id,
        before: ['email' => $other->email],
        after: ['email' => $other->email],
        authorizationBasis: 'operator_console',
    )->id;

    app(AnonymiseCustomer::class)->handle($customer->id);

    // The Customer's own audit snapshots are nulled; the append-only row itself SURVIVES (never deleted). The
    // other Customer's snapshot is intact — the redaction is scoped.
    $redacted = AuditRecord::findOrFail($auditId);
    expect($redacted->before)->toBeNull()
        ->and($redacted->after)->toBeNull()
        ->and(AuditRecord::query()->whereKey($auditId)->exists())->toBeTrue()
        ->and(AuditRecord::findOrFail($otherAuditId)->before)->toBe(['email' => $other->email]);

    // Immutability intact: a STRUCTURAL update on the redacted row is STILL rejected (the redaction did not
    // loosen the trigger). Wrapped in a nested transaction so a PostgreSQL trigger-abort rolls back to the
    // savepoint and the outer RefreshDatabase transaction survives (the ImmutabilityTest idiom).
    $message = '';
    try {
        DB::transaction(fn () => DB::table('audit_records')->where('id', $auditId)->update(['action' => 'tampered.action']));
    } catch (QueryException $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('immutable')
        ->and(AuditRecord::findOrFail($auditId)->action)->toBe('parties.customer.example');
});
