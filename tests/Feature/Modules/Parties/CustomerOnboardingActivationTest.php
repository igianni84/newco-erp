<?php

use App\Modules\Parties\Actions\ActivateCustomer;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerActivated;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer onboarding activation transition (parties-membership-activation; design L6/L8; party-registry —
 * Requirements: Customer Onboarding Activation, Demand-Side Activation Events). It drives the REAL
 * {@see ActivateCustomer} Action and asserts the emergent contract:
 *   - `pending → active` is the SOLE writer of that transition and records exactly one ROOT {@see CustomerActivated}
 *     (module `parties`, entity_type `Customer`, PII-free payload `{customer_id, status}`), and performs NO Account
 *     transition — the co-provisioned Account stays `active` (§ 4.7 / AC-K-FSM-9);
 *   - the COMPOSITE onboarding gate (design L6; § 4.1 / AC-K-J-1 + AC-K-BR-Identity-3) is a hard conjunction: email
 *     verified ∧ T&C ∧ privacy accepted ∧ sanctions `passed` ∧ KYC cleared-where-required (NULL kyc = cleared,
 *     DEC-071). Any one unmet condition rejects with {@see IllegalCustomerTransition::gateNotMet()} (the PII-free
 *     reason), leaving `status = pending` and the event log empty;
 *   - activation is EXPLICIT, never auto-driven (design L6; § 9.4; AC-K-BR-Customer-1): recording a sanctions
 *     screening `passed` does NOT flip `status` — only the explicit Action does (the status FSM is independent of
 *     the sanctions/KYC FSMs);
 *   - the transition is from-state guarded: a call on a Customer not in `pending` throws
 *     {@see IllegalCustomerTransition::cannotActivate()} BEFORE the gate is evaluated and the transaction rolls back.
 *
 * RefreshDatabase per the directory convention; the Action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper (the event being recorded at all
 * is proof of the in-transaction wiring). Events are asserted BY NAME and the payload BY KEY — never a byte-compare
 * of stored jsonb (PG reorders keys — knowledge/testing trap 3) — so the file holds on PostgreSQL 17. The two
 * rejection paths are distinguished by the exception MESSAGE ("only from pending" vs "onboarding gate") so each
 * negative test pins the precise guard that fired.
 */
uses(RefreshDatabase::class);

it('activates a fully-gated pending Customer, records one root CustomerActivated, and leaves the Account active', function () {
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
        // kyc_required / kyc_status left NULL — an un-screened Customer is not KYC-gated (DEC-071).
    ]);
    // The co-provisioned billing Account is born `active`; activation must not touch it (§ 4.7 / AC-K-FSM-9).
    $account = Account::factory()->create(['customer_id' => $customer->id]);

    $returned = app(ActivateCustomer::class)->handle($customer->id);

    // The Customer transitions to `active` (returned model + the persisted row).
    expect($returned->status)->toBe(CustomerStatus::Active)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active);

    // The Account status is unchanged — Customer activation performs no Account transition.
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Active);

    // Exactly one domain event total — the factories bypass the Create*/Activate actions and record nothing, so the
    // only event is the CustomerActivated from this transition.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', CustomerActivated::NAME)->sole();

    expect($event->module)->toBe('parties')                      // Module::Parties->value
        ->and($event->entity_type)->toBe('Customer')             // the activation is a Customer-status event
        ->and($event->entity_id)->toBe((string) $customer->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);      // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the {customer_id, status}
    // shape, pinned so the PII-free contract cannot silently widen. `customer_id` decodes from jsonb as a reliable
    // PHP int (trap 3) → `toBe`; `status` is the post-transition business enum value.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['customer_id', 'status']);
    expect($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['status'])->toBe('active');

    // PII-free: no name/email/phone/DOB and no acceptance-timestamp leaks into the 10-year audit store.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email')
        ->and($event->payload)->not->toHaveKey('email_verified_at');

    // The activation is a ROOT event: it records no parent in its transaction.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects activation when any single onboarding data gate is unmet, leaving the Customer pending with no event', function (string $field, mixed $value) {
    // A baseline fully-gated pending Customer, with exactly ONE acceptance/sanctions condition knocked into an unmet
    // state (the dynamic `$field => $value` overrides one literal key), so each case isolates a single gate.
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
        $field => $value,
    ]);

    // The gate (not the from-state guard) rejects — the Customer IS `pending`, so "onboarding gate" pins the path.
    expect(fn () => app(ActivateCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class, 'onboarding gate');

    // The gate fires before any write and the transaction rolls back: status unchanged, no event recorded.
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'email not verified' => ['email_verified_at', null],
    'T&C not accepted' => ['tc_accepted_at', null],
    'privacy not accepted' => ['privacy_accepted_at', null],
    'sanctions still pending' => ['sanctions_status', SanctionsStatus::Pending],
    'sanctions failed' => ['sanctions_status', SanctionsStatus::Failed],
    'sanctions un-screened (null)' => ['sanctions_status', null],
]);

it('rejects activation when KYC is required but not cleared, leaving the Customer pending with no event', function (KycStatus $kyc) {
    // Fully gated on email / T&C / privacy / sanctions, but `kyc_required` with a blocking `kyc_status`
    // (`pending` / `rejected`) fails the KYC-cleared rider (AC-K-BR-Identity-3) — the complement of the cleared cases.
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
        'kyc_required' => true,
        'kyc_status' => $kyc,
    ]);

    expect(fn () => app(ActivateCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class, 'onboarding gate');

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'kyc required but pending' => [KycStatus::Pending],
    'kyc required but rejected' => [KycStatus::Rejected],
]);

it('clears KYC where required when kyc_status is verified or not_required, and treats a NULL kyc_status as cleared (DEC-071)', function (?KycStatus $kyc) {
    // The KYC-cleared rider (AC-K-BR-Identity-3): with kyc_required set, a `verified`/`not_required`/NULL status
    // clears — so a fully-gated Customer activates. (`pending`/`rejected` are the blocking cases above.)
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
        'kyc_required' => true,
        'kyc_status' => $kyc,
    ]);

    app(ActivateCustomer::class)->handle($customer->id);

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(1);
})->with([
    'kyc verified' => [KycStatus::Verified],
    'kyc not_required' => [KycStatus::NotRequired],
    'kyc null (un-screened, cleared per DEC-071)' => [null],
]);

it('rejects activating a Customer not in pending, leaving it unchanged with no event', function (CustomerStatus $state) {
    // Fully gated, so it is the FROM-STATE guard — not the onboarding gate — that rejects (it fires first).
    $customer = Customer::factory()->create([
        'status' => $state,
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    expect(fn () => app(ActivateCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class, 'only from pending');

    expect(Customer::findOrFail($customer->id)->status)->toBe($state)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active' => [CustomerStatus::Active],        // already activated — no re-activation
    'suspended' => [CustomerStatus::Suspended],
    'closed' => [CustomerStatus::Closed],
]);

it('does not auto-activate on a sanctions pass — activation is the explicit Action, not an FSM side-effect', function () {
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
    ]);

    // A passed onboarding screening makes the Customer fully gated, but the sanctions FSM is independent of the
    // status FSM (§ 9.4; AC-K-BR-Customer-1): it records its own event and performs NO status transition.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(0);

    // Only the EXPLICIT Action flips the now-fully-gated Customer to `active` and records the activation event.
    app(ActivateCustomer::class)->handle($customer->id);

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(DomainEvent::query()->where('name', CustomerActivated::NAME)->count())->toBe(1);
});
