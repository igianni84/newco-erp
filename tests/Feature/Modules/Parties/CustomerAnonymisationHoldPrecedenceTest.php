<?php

use App\Modules\Parties\Actions\AnonymiseCustomer;
use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerAnonymised;
use App\Modules\Parties\Exceptions\AnonymisationBlockedByComplianceHold;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the FULL per-Hold-type anonymisation precedence matrix (parties-anonymisation task 4.1, design D2;
 * party-registry — Requirement: Anonymisation Hold Precedence; AC-K-J-9a; canon MVP-DEC-015 — ADR
 * decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md). Where {@see CustomerAnonymisationTest} pins
 * the erasure CORE (overwrite / stamp / audit-redaction / event) with two representative gate smoke cases, this
 * file is the exhaustive precedence companion: it walks EVERY {@see HoldType} and asserts the gate's verdict.
 *
 * The gate is canon MVP-DEC-015 — `compliance`-only and COUNT-INDEPENDENT: anonymisation is blocked IFF an active
 * `compliance` Hold covers the Customer, and NO other type blocks (the gate keys on the PRESENCE of `compliance`
 * among possibly-many concurrent Holds — BR-K-Hold-1 — never on "the sole Hold", so it is immune to the RM-04 6→8
 * Hold-count debt). This file proves that contract three ways:
 *   - the per-type matrix — a single `compliance` Hold blocks (Customer left entirely un-anonymised, NO
 *     {@see CustomerAnonymised} event), every one of the other seven types is transparent (anonymisation proceeds),
 *     and in BOTH branches the Hold's own `active` blocking state is preserved (the gate READS coverage, it never
 *     lifts or mutates a Hold — invariant 7: Holds are never auto-lifted);
 *   - the lift-then-retry — a `compliance`-blocked Customer unblocks once the Hold is lifted through the real
 *     operator path ({@see LiftHold}) and anonymisation completes (the spec scenario "Lifting the blocking
 *     compliance Hold unblocks anonymisation");
 *   - precedence dominance — `compliance` dominates a COEXISTING non-`compliance` Hold; lifting only the
 *     `compliance` Hold lets anonymisation proceed while the non-`compliance` Hold survives `active`.
 *
 * Fixtures follow the module split (the {@see HoldFactory} docblock): a Hold is PLACED via the factory (a pure
 * fixture that bypasses `PlaceHold`'s status-coupling suspend, so the un-`suspended` Customer isolates the gate
 * read — `DatabaseComplianceStatusReader` reads `parties_holds` directly), and LIFTED via the REAL {@see LiftHold}
 * Action (a lifecycle transition; the un-`suspended` Customer means LiftHold's restore leg is a no-op, so the lift
 * simply moves the Hold `active → lifted`). RefreshDatabase per the directory convention; the Action opens its own
 * `DB::transaction`. Events are asserted BY NAME (never a byte-compare of stored jsonb — PG reorders keys) and
 * persisted rows are re-fetched, so the file holds identically on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('blocks anonymisation iff the covering Hold is compliance — the per-Hold-type precedence matrix', function (HoldType $type, bool $blocks) {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $originalEmail = $customer->email;

    // A single active Hold of this type on the Customer scope (placed via the fixture factory — no suspend
    // coupling — so the gate read is isolated to the Hold-type verdict).
    $hold = Hold::factory()->create([
        'hold_type' => $type,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    if ($blocks) {
        // `compliance` is the SOLE blocking type: the gate throws BEFORE any write, leaving the Customer entirely
        // un-anonymised (`anonymised_at` still NULL, PII intact) and recording NO CustomerAnonymised event.
        expect(fn () => app(AnonymiseCustomer::class)->handle($customer->id))
            ->toThrow(AnonymisationBlockedByComplianceHold::class);

        $fresh = Customer::findOrFail($customer->id);
        expect($fresh->anonymised_at)->toBeNull()
            ->and($fresh->email)->toBe($originalEmail);
        expect(DomainEvent::query()->where('name', CustomerAnonymised::NAME)->count())->toBe(0);
    } else {
        // Every non-`compliance` type is transparent to the gate: anonymisation proceeds (PII overwritten with the
        // id-derived placeholder, `anonymised_at` stamped, exactly one CustomerAnonymised recorded).
        app(AnonymiseCustomer::class)->handle($customer->id);

        $fresh = Customer::findOrFail($customer->id);
        expect($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($fresh->email)->toBe("anonymised+{$customer->id}@anonymised.invalid");
        expect(DomainEvent::query()->where('name', CustomerAnonymised::NAME)->count())->toBe(1);
    }

    // In BOTH branches the Hold's structural blocking state is PRESERVED — the gate reads coverage, it never lifts
    // or mutates a Hold (invariant 7: Holds are never auto-lifted). A blocking compliance Hold stays active; a
    // transparent non-compliance Hold survives anonymisation active.
    expect(Hold::findOrFail($hold->id)->status)->toBe(HoldStatus::Active);
})->with([
    'admin                  → proceeds' => [HoldType::Admin, false],
    'kyc                    → proceeds' => [HoldType::Kyc, false],
    'payment                → proceeds' => [HoldType::Payment, false],
    'fraud                  → proceeds' => [HoldType::Fraud, false],
    'compliance             → BLOCKS' => [HoldType::Compliance, true],
    'credit                 → proceeds' => [HoldType::Credit, false],
    'chargeback_review      → proceeds' => [HoldType::ChargebackReview, false],
    'storage_payment_failed → proceeds' => [HoldType::StoragePaymentFailed, false],
]);

it('enumerates the complete HoldType domain in the precedence matrix — a new type cannot silently escape the gate', function () {
    // Ties the matrix above to the enum: only `compliance` blocks (canon MVP-DEC-015), so any NEW HoldType would
    // default to "proceeds" — but that must be a DELIBERATE matrix row, not an accidental omission. This guard
    // reds the day HoldType grows, forcing the new case into the matrix (the SupplyLifecycleChainTest
    // exhaustive-set-as-tripwire discipline).
    expect([
        HoldType::Admin, HoldType::Kyc, HoldType::Payment, HoldType::Fraud,
        HoldType::Compliance, HoldType::Credit, HoldType::ChargebackReview, HoldType::StoragePaymentFailed,
    ])->toEqualCanonicalizing(HoldType::cases());
});

it('unblocks anonymisation once the blocking compliance Hold is lifted', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $hold = Hold::factory()->create([
        'hold_type' => HoldType::Compliance,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    // Blocked while the compliance Hold is active — the gate throws before any write.
    expect(fn () => app(AnonymiseCustomer::class)->handle($customer->id))
        ->toThrow(AnonymisationBlockedByComplianceHold::class);
    expect(Customer::findOrFail($customer->id)->anonymised_at)->toBeNull();

    // Lift the compliance Hold through the REAL operator path (`compliance` is operator-liftable, not auto-managed
    // — HoldType::autoLiftable() is false). The Customer was never suspended (the factory bypasses PlaceHold), so
    // LiftHold's restore leg is a no-op; the lift simply moves the Hold active → lifted.
    app(LiftHold::class)->handle($hold->id);
    expect(Hold::findOrFail($hold->id)->status)->toBe(HoldStatus::Lifted);

    // With the compliance Hold lifted, coverage is clear and anonymisation now proceeds to completion.
    app(AnonymiseCustomer::class)->handle($customer->id);
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->email)->toBe("anonymised+{$customer->id}@anonymised.invalid");
    expect(DomainEvent::query()->where('name', CustomerAnonymised::NAME)->count())->toBe(1);
});

it('lets compliance precedence dominate a coexisting non-compliance Hold, which survives once compliance is lifted', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    // Two concurrent active Holds on the Customer (BR-K-Hold-1 admits many): one compliance, one payment.
    $compliance = Hold::factory()->create([
        'hold_type' => HoldType::Compliance,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);
    $payment = Hold::factory()->create([
        'hold_type' => HoldType::Payment,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    // Compliance PRECEDENCE dominates: the presence of the compliance Hold blocks even though a non-compliance
    // Hold coexists (the gate keys on presence, not on being the only Hold — count-independent).
    expect(fn () => app(AnonymiseCustomer::class)->handle($customer->id))
        ->toThrow(AnonymisationBlockedByComplianceHold::class);
    expect(Customer::findOrFail($customer->id)->anonymised_at)->toBeNull();

    // Lift ONLY the compliance Hold — the payment Hold stays active. Anonymisation now proceeds (compliance-only,
    // count-independent), and the surviving non-compliance Hold's blocking state is preserved through anonymisation.
    app(LiftHold::class)->handle($compliance->id);

    app(AnonymiseCustomer::class)->handle($customer->id);
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and(Hold::findOrFail($payment->id)->status)->toBe(HoldStatus::Active);
    expect(DomainEvent::query()->where('name', CustomerAnonymised::NAME)->count())->toBe(1);
});
