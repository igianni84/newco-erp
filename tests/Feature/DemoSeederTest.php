<?php

// RM-07 (docs/validation/Remediation_Tracker.md) — the DemoSeeder is now self-provisioning (chains RoleSeeder
// + OperatorDemoSeeder) and stands up the SoD / rejection walkthrough fixture: ONE Product Master carried
// through the REAL domain actions (created as the creator, submitted as the reviewer) so it sits in `reviewed`
// with GENUINE creator/reviewer lineage the ApprovalGovernance floor reads. The bulk demo data is still
// direct-`create()`d (arbitrary lifecycle states, no 3-operator ceremony), but a directly-seeded `reviewed`
// row carries NO lineage, so a distinct-actor activation would pass VACUOUSLY — demonstrating nothing. These
// tests pin that the walkthrough is real end-to-end: the creator is blocked from approving its own Master and
// a DISTINCT approver activates it through the console; the row also accepts a console rejection.
//
// RM-08 (parties-producer-approval-sod) adds a SECOND real-lineage fixture: ONE Producer carried through the
// REAL CreateProducer action as the creator, left `draft` + KYC-cleared, so the Parties ProducerApprovalGovernance
// floor reads its creator from the `ProducerCreated` event. The Producer FSM is linear (no reviewer leg) → a
// 2-step Creator → Approver walkthrough: the creator is blocked from activating its own Producer and a DISTINCT
// approver activates it through the console. A directly-seeded draft (e.g. the Leflaive demo row) carries no
// lineage, so a distinct-actor activation would pass VACUOUSLY there.
//
// DatabaseMigrations (like the lifecycle console tests): the SoD fixtures and the console activations each drive
// a domain action that opens its OWN DB::transaction; RefreshDatabase would wrap them in a never-committed
// outer transaction and defeat the recorder's transaction-level guard (the faithful production shape).

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Exceptions\SeparationOfDutiesViolation;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\DomainEvent;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(DatabaseMigrations::class);

/** The reviewable SoD fixture created through the real domain actions — resolved by its stable seeded name. */
function demoSodMaster(): ProductMaster
{
    return ProductMaster::query()->where('name', DemoSeeder::SOD_FIXTURE_MASTER_NAME)->sole();
}

/** The draft Producer SoD fixture created through the real CreateProducer action — resolved by its stable seeded name. */
function demoSodProducer(): Producer
{
    return Producer::query()->where('name', DemoSeeder::SOD_FIXTURE_PRODUCER_NAME)->sole();
}

it('yields at least two distinct operator logins', function () {
    seed(DemoSeeder::class);

    expect(Operator::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Operator::query()->pluck('email')->unique()->count())->toBeGreaterThanOrEqual(2);
});

it('stands up a reviewable Product Master carrying real creator lineage under an active-projected producer', function () {
    seed(DemoSeeder::class);

    $master = demoSodMaster();
    expect($master->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // The creation event carries a real operator actor_id — the creator lineage the SoD floor reads back.
    $created = DomainEvent::query()
        ->where('entity_type', 'ProductMaster')
        ->where('entity_id', (string) $master->id)
        ->where('name', 'ProductMasterCreated')
        ->sole();
    expect($created->actor_id)->not->toBeNull();

    // Its producer is active-projected, so a distinct approver's live activation clears the producer gate.
    $producerState = ProducerState::query()->where('producer_id', $master->producer_id)->sole();
    expect($producerState->status)->toBe(ProducerProjectionStatus::Active);
});

it('blocks the fixture creator from approving its own Master (separation of duties)', function () {
    seed(DemoSeeder::class);

    $master = demoSodMaster();
    $creator = Operator::query()->where('email', 'creator@newco.test')->firstOrFail();

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);
});

it('activates the seeded reviewable Master through the console for a distinct approver', function () {
    seed(DemoSeeder::class);

    $master = demoSodMaster();
    $approver = Operator::query()->where('email', 'approver@newco.test')->firstOrFail();

    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.activated'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active);
});

it('records a console rejection with notes on the seeded fixture, keeping it reviewed', function () {
    seed(DemoSeeder::class);

    $master = demoSodMaster();
    $reviewer = Operator::query()->where('email', 'reviewer@newco.test')->firstOrFail();

    actingAs($reviewer, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('reject', ['notes' => 'Vintage documentation incomplete.'])
        ->assertNotified((string) __('operator_console.product_master.notifications.rejected'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);
});

// --- RM-08 (parties-producer-approval-sod): the Producer activation SoD fixture ------------------------------

it('stands up a draft, KYC-cleared Producer carrying real creator lineage for the activation walkthrough', function () {
    seed(DemoSeeder::class);

    $producer = demoSodProducer();
    expect($producer->status)->toBe(ProducerStatus::Draft)
        ->and($producer->kyc_status)->toBe(KycStatus::Verified); // KYC-cleared → the SoD floor is the only block on the creator.

    // The creation event carries a real operator actor_id — the creator lineage the SoD floor reads back. A
    // directly-seeded draft (e.g. the Leflaive demo producer) would carry no such event, so it would prove nothing.
    $created = DomainEvent::query()
        ->where('entity_type', 'Producer')
        ->where('entity_id', (string) $producer->id)
        ->where('name', 'ProducerCreated')
        ->sole();
    expect($created->actor_id)->not->toBeNull();

    // That creator actor is the seeded `creator@newco.test` persona — genuine lineage, not a null-creator vacuum.
    $creator = Operator::query()->where('email', 'creator@newco.test')->firstOrFail();
    expect($created->actor_id)->toEqual($creator->id); // loose: PG returns a numeric string for the uncast bigint.
});

it('blocks the fixture creator from activating its own Producer (separation of duties)', function () {
    seed(DemoSeeder::class);

    $producer = demoSodProducer();
    $creator = Operator::query()->where('email', 'creator@newco.test')->firstOrFail();

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(SeparationOfDutiesViolation::class);

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
});

it('activates the seeded draft Producer through the console for a distinct approver', function () {
    seed(DemoSeeder::class);

    $producer = demoSodProducer();
    $approver = Operator::query()->where('email', 'approver@newco.test')->firstOrFail();

    actingAs($approver, 'operator');
    Livewire::test(ViewProducer::class, ['record' => $producer->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.activated'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);
});
