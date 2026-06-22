<?php

// Tasks 2.1–3.3 (operator-console-parties-kyc-sanctions; design D2/D3/D4/D5/D6/D7/D8) — the Customer console's
// KYC + sanctions compliance-WRITE surface on ViewCustomer. The three form-less KYC verbs (requireKyc,
// recordKycVerified, recordKycRejected) and the one form-bearing sanctions verb (recordScreening) the page APPENDS
// to its SurfacesDomainActions-built header-action array (design D2/D3), each routing through a Parties domain
// action by the customer id and NEVER writing the model itself (the no-Eloquent-write rule).
//
// THE KYC VERBS ARE VISIBILITY-GATED to their legal `kyc_status` from-state (design D4): requireKyc iff
// NULL/not_required, recordKycVerified/recordKycRejected iff pending. Because the visibility predicate is the EXACT
// COMPLEMENT of the domain from-state guard, a rejected transition is UNREACHABLE through the surface — the verb is
// simply hidden; its reject is proven by a domain toThrow + assertActionHidden (task 2.3), never an action_failed
// the page can't raise (the Filament hidden-action landmine, lessons.md 2026-06-22). THIS task (2.1) pins the
// VISIBILITY contract; 2.2/2.3 add the write-through + reject-floor, 3.x the sanctions form.
//
// THE KYC VERBS ARE EVENT-SILENT (design D7): the only events are the coupled CustomerHoldPlaced/Lifted (from the
// auto-Hold) + CustomerSuspended/Reactivated (from the coupling); RecordKycRejected records NOTHING. No
// CustomerKyc* event exists — the catalog names none (asserted in 2.2/2.3).
//
// DatabaseMigrations (mirroring ProducerKycConsoleTest + CustomerLifecycleConsoleTest): each console action drives
// a real domain action opening its OWN DB::transaction, so the in-transaction event append commits for real
// (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory bypasses the
// actions → records no event, co-provisions no Account/Profile. Parties enums/models are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('shows requireKyc only from a requirable kyc_status — NULL or not_required (design D4)', function (?KycStatus $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // requireKyc OPENS the KYC FSM (→ pending); it is reachable only from un-screened (NULL — DEC-071) or the
    // explicit not_required. The verb is visible iff kycRequirable() holds — the complement of RequireKyc's guard.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    if ($visible) {
        $component->assertActionVisible('requireKyc');
    } else {
        $component->assertActionHidden('requireKyc');
    }
})->with([
    'never-screened (NULL) → visible' => [null, true],
    'not_required → visible' => [KycStatus::NotRequired, true],
    'pending → hidden' => [KycStatus::Pending, false],
    'verified → hidden' => [KycStatus::Verified, false],
    'rejected → hidden' => [KycStatus::Rejected, false],
]);

it('shows recordKycVerified and recordKycRejected only from pending (design D4)', function (?KycStatus $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // Verify and reject are each reachable ONLY from `pending` (§ 9.1); each is visible iff kycPending() — the
    // complement of RecordKycVerified's / RecordKycRejected's domain guard. Both are visible together when pending.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    if ($visible) {
        $component->assertActionVisible('recordKycVerified')
            ->assertActionVisible('recordKycRejected');
    } else {
        $component->assertActionHidden('recordKycVerified')
            ->assertActionHidden('recordKycRejected');
    }
})->with([
    'pending → visible' => [KycStatus::Pending, true],
    'never-screened (NULL) → hidden' => [null, false],
    'not_required → hidden' => [KycStatus::NotRequired, false],
    'verified → hidden' => [KycStatus::Verified, false],
    'rejected → hidden' => [KycStatus::Rejected, false],
]);
