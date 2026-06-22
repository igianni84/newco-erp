<?php

// Task 5.1 (operator-console-parties-customer; design D5/D7/D9; ADR 2026-06-19 + 2026-06-20 + 2026-06-21; the
// change's CLOSING integration proof for the Customer console) — one feature test driving a Customer through the
// WHOLE console slice end-to-end through the PAGES (not the raw Actions), exactly as a human operator would demo
// it. It asserts the EMERGENT event SET over the entire run (the closing-integration rule, knowledge/testing/
// rules.md), proving two things that hold over the COMPOSED chain which no single per-task test asserts alone:
//   1. the emergent set is EXACTLY CustomerCreated / CustomerActivated / CustomerSuspended / CustomerReactivated /
//      CustomerClosed — the gate-UNMET activate in part (a) added nothing (rejected, its transaction rolled back);
//      the Account leg of CreateCustomer is event-silent (no AccountCreated exists — design D7); and the
//      profile-LESS gate-met seed in part (b) kept the suspend/reactivate Profile cascade silent (that cascade is
//      the Action's behaviour over a customer's profiles, covered by parties-core — design D9). So domain_events
//      holds ONLY the five Customer console writes — no Profile cascade rows, no Account-leg event.
//   2. EVERY recorded event is a Parties console-driven write carrying the operator audit envelope (module
//      `parties`, actor_role newco_ops, a non-null operator actor) — proven SET-WIDE, then concretely tied to the
//      acting operator on representative writes spanning BOTH surfaces (the create page + a view-page verb).
//
// THE CROSS-SLICE ACTIVATION GATE (design D5) is exercised end-to-end here: part (a) creates a Customer through the
// CreateCustomer page (born `pending`, gate-UNMET — the console sets none of the onboarding-acceptance / sanctions
// columns the gate reads), then attempts `activate` and watches it reject gracefully (a danger notification, no
// event, still `pending`) — the console is a surface ahead of its drivers, not a bug. Part (b) seeds a gate-MET
// Customer (the three onboarding acceptances + sanctions passed; kyc_required left null → the KYC rider clears) to
// drive the full activate → suspend → reactivate → close FSM that the gate-unmet path cannot reach.
//
// DatabaseMigrations (mirroring the per-task console tests + ClubConsoleChainTest): each console action drives a
// real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append commits
// for real (RefreshDatabase would wrap every write in a never-committed outer transaction). The gate-met Customer
// is seeded EVENT-FREE via Customer::factory() (the factory bypasses the actions, records no event, co-provisions
// no Account and creates no Profile), so the only events are the ones the console actions record. Parties
// enums/models/pages are imported freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel
// PRODUCTION code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate): the uncast `actor_id` bigint reads back as a numeric string on
// PostgreSQL, so it is asserted with loose `toEqual`.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\CreateCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the entire Customer console slice end-to-end as an operator demo, asserting the emergent event set and the newco_ops envelope on every write', function () {
    // ONE operator drives the whole demo — Customer lifecycle is single-operator (no separation of duties, design
    // D3), so no distinct lineage is needed. Every event below must carry this operator's id (actor_role
    // newco_ops), resolved by the actions from the `operator` guard.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // ══ PART (a) — the create page + the cross-slice activation gate (design D5) ══════════════════════════════
    // CREATE through the console page → a Customer born `pending`, 1 CustomerCreated (the co-provisioned Account
    // leg is event-silent — design D7). The console sets none of the onboarding-acceptance / sanctions columns,
    // so this Customer is gate-UNMET for activation.
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'email' => 'chain.console.customer@example.test',
            'name' => 'Chain Console Customer',
            'preferred_currency' => 'EUR',
            'preferred_locale' => 'en',
            'phone' => '+39 02 7654321',
            'date_of_birth' => '1980-03-22',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Customer::query()->where('email', 'chain.console.customer@example.test')->sole();
    expect($created->status)->toBe(CustomerStatus::Pending);

    // ACTIVATE the freshly-created Customer → the composite onboarding gate blocks it (gate-UNMET): a danger
    // notification, NO state change, and NO event recorded (the rejected transaction rolled back). This documents
    // D5 — the console surfaces the verb ahead of the consumer-onboarding flow + compliance console that set the
    // gate columns; it is correct domain behaviour, not a bug.
    Livewire::test(ViewCustomer::class, ['record' => $created->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.customer.notifications.action_failed'));

    expect(Customer::findOrFail($created->id)->status)->toBe(CustomerStatus::Pending);

    // The gate-unmet activate added NOTHING — only the create's CustomerCreated exists so far. Pinning it here
    // localises D5: were the gate to stop rejecting, this fails at the point of cause, before the emergent-set
    // assertion below muddies the diagnosis.
    expect(DomainEvent::query()->pluck('name')->all())->toEqual(['CustomerCreated']);

    // ══ PART (b) — the full status FSM on a gate-MET, profile-less seed ═══════════════════════════════════════
    // A gate-MET `pending` Customer: the three onboarding acceptances set, sanctions `passed`, `kyc_required` left
    // NULL so the KYC rider short-circuits (DEC-071). The factory bypasses CreateCustomer → it records no event,
    // co-provisions no Account, and creates NO Profile, so (i) the gate-met activate's CustomerActivated is the
    // only activation event and (ii) the profile-LESS record keeps the suspend/reactivate Profile cascade silent
    // (design D9) — the emergent set stays exactly the five Customer events.
    $gateMet = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    // Drive the full FSM through the view page — activate `pending → active`, suspend `active → suspended`,
    // reactivate `suspended → active`, close `active → closed`. The console never writes `status`; each verb routes
    // through its domain action, which records the lifecycle event and advances the state.
    Livewire::test(ViewCustomer::class, ['record' => $gateMet->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.customer.notifications.activated'));
    expect(Customer::findOrFail($gateMet->id)->status)->toBe(CustomerStatus::Active);

    Livewire::test(ViewCustomer::class, ['record' => $gateMet->id])
        ->callAction('suspend')
        ->assertNotified((string) __('operator_console.customer.notifications.suspended'));
    expect(Customer::findOrFail($gateMet->id)->status)->toBe(CustomerStatus::Suspended);

    Livewire::test(ViewCustomer::class, ['record' => $gateMet->id])
        ->callAction('reactivate')
        ->assertNotified((string) __('operator_console.customer.notifications.reactivated'));
    expect(Customer::findOrFail($gateMet->id)->status)->toBe(CustomerStatus::Active);

    Livewire::test(ViewCustomer::class, ['record' => $gateMet->id])
        ->callAction('close')
        ->assertNotified((string) __('operator_console.customer.notifications.closed'));
    expect(Customer::findOrFail($gateMet->id)->status)->toBe(CustomerStatus::Closed);

    // ══ Emergent event-SET proof over the WHOLE demo ═════════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY the five Customer console writes. The gate-unmet activate in part (a) added
    //     nothing; the Account leg of CreateCustomer is event-silent (design D7); the profile-less seed kept the
    //     suspend/reactivate Profile cascade silent (design D9). Nothing else leaked across the composed chain.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing([
            'CustomerCreated',
            'CustomerActivated',
            'CustomerSuspended',
            'CustomerReactivated',
            'CustomerClosed',
        ]);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(5);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the actor_id is concretely the ACTING operator on representative writes spanning BOTH surfaces — the
    //     create page (CustomerCreated) and a view-page lifecycle verb (CustomerClosed). Loose toEqual is the
    //     proven idiom: the uncast bigint reads back as a numeric string on PG, never strict-compare it.
    $createdEvent = DomainEvent::query()->where('name', 'CustomerCreated')->sole();
    $closedEvent = DomainEvent::query()->where('name', 'CustomerClosed')->sole();
    expect($createdEvent->actor_id)->toEqual($operator->id)
        ->and($closedEvent->actor_id)->toEqual($operator->id);
});
