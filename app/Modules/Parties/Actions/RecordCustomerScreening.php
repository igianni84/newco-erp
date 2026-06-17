<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerOnboardingScreeningFailed;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerRescreeningFailed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Exceptions\IllegalSanctionsTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records one sanctions-screening verdict on a Customer — sets `sanctions_status`, stamps the screening window
 * (`last_screening_at` / `next_rescreen_at`), records the `screening_trigger_source`, and (on a `passed`/`failed`
 * completion) records the matching § 15.6 screening event — atomically (parties-compliance, design L4/L6/L8;
 * party-registry — Requirements: Customer Sanctions Screening Lifecycle, Sanctions Screening Events).
 *
 * This action is the SOLE writer of the Customer sanctions fields and the SINGLE writer of the four screening
 * events. It is **manual-first** (design L6, § 9.5): the verdict is an operator INPUT, not the result of a
 * vendor call — no screening-vendor adapter, HTTP client or provider config is built here (a documented seam).
 *
 * Unlike the KYC actions (which record no event — design L3), sanctions screening EMITS, so — like the spine
 * transition actions `ActivateProducer`/`SunsetClub` — it injects the {@see DomainEventRecorder} and resolves the
 * acting operator from the {@see ActorContext} seam (`ActorRole::System` until real principals wire in, design L8).
 *
 * The sanctions FSM is `pending → passed | failed | under_review`, `under_review → passed | failed` (§ 9.2),
 * SEPARATE from the Customer status FSM and INDEPENDENT of the KYC FSM (§ 9.4): a screening NEVER touches
 * `kyc_status`. The only hard from-state guard is the **onboarding-is-first** invariant (design L4): a verdict
 * carrying `trigger_source = onboarding` requires `last_screening_at IS NULL` — else
 * {@see IllegalSanctionsTransition::onboardingAlreadyScreened()}. Re-screens (any other trigger source) are
 * intentionally admissible from any prior state, because a re-screen can legitimately flip a verdict (e.g.
 * `passed → failed`) or resolve an open `under_review`.
 *
 * `last_screening_at` and `next_rescreen_at` are stamped from ONE captured instant, so the next re-screen moment
 * is exactly 12 months forward — the scheduled moment the deferred daily cadence job will query (the job that
 * reads `next_rescreen_at`, the AML-threshold cumulative scan, and the enhanced-KYC detection are all deferred
 * automation — design L4/L6/L7 seams; this action is the operator ad-hoc path that ships now).
 *
 * Event selection (design L4): a `passed`/`failed` verdict is a **completion** and records exactly one event,
 * whose FAMILY is the screening phase — `trigger_source = onboarding` → `CustomerOnboardingScreening{Passed,Failed}`,
 * any other (re-screen) source → `CustomerRescreening{Passed,Failed}`. An `under_review` verdict is NOT a
 * completion and records NO event (the § 15.6 catalog names only the Passed/Failed pairs); a later resolution to
 * `passed`/`failed` records the corresponding `CustomerRescreening*` event. Each event class is the single source
 * of truth for its name, entity type and PII-free payload, so this action stays thin and magic-string-free.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single
 * writer — the guard carries correctness either way), asserts the onboarding-is-first invariant, then writes the
 * sanctions fields and records the completion event in the SAME transaction (the recorder's open-transaction
 * guard makes write + emit atomic; the payload reflects the POST-screening state). A rejected onboarding call
 * throws and the transaction rolls back, leaving the sanctions state and the event log unchanged. `version` is
 * NOT bumped — it is reserved for identity-attribute revisions (its parties-core meaning); a screening is not one.
 * The Model stays persistence-only; this action is the only sanctions writer.
 */
class RecordCustomerScreening
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId, SanctionsStatus $verdict, ScreeningTriggerSource $source): Customer
    {
        return DB::transaction(function () use ($customerId, $verdict, $source): Customer {
            // Transaction-locked re-read so two concurrent screenings serialize on PostgreSQL; the onboarding
            // guard below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // An onboarding screening MUST be the Customer's FIRST (design L4). Re-screens (any other trigger
            // source) are admissible from any prior state — a re-screen can flip a verdict or resolve a review —
            // so they are intentionally not from-state guarded here.
            if ($source === ScreeningTriggerSource::Onboarding && $customer->last_screening_at !== null) {
                throw IllegalSanctionsTransition::onboardingAlreadyScreened();
            }

            // One captured instant: `next_rescreen_at` is exactly 12 months past `last_screening_at` — the
            // scheduled re-screen moment the deferred daily cadence job will query (design L4/L6 seam).
            $screenedAt = CarbonImmutable::now();

            $customer->update([
                'sanctions_status' => $verdict,
                'last_screening_at' => $screenedAt,
                'next_rescreen_at' => $screenedAt->addMonths(12),
                'screening_trigger_source' => $source,
            ]);

            // A completion (passed/failed) records its event by phase: onboarding → CustomerOnboardingScreening*,
            // any re-screen → CustomerRescreening*. `under_review` (and the non-verdict `pending`) is not a
            // completion and records nothing (design L4); a later resolution records the rescreening event.
            $event = match (true) {
                $verdict === SanctionsStatus::Passed => $source === ScreeningTriggerSource::Onboarding
                    ? CustomerOnboardingScreeningPassed::class
                    : CustomerRescreeningPassed::class,
                $verdict === SanctionsStatus::Failed => $source === ScreeningTriggerSource::Onboarding
                    ? CustomerOnboardingScreeningFailed::class
                    : CustomerRescreeningFailed::class,
                default => null,
            };

            if ($event !== null) {
                // No causation/correlation passed → the recorder makes this a root event: a screening verdict is
                // operator-initiated, never a cascade step in this slice.
                $this->recorder->record(
                    name: $event::NAME,
                    module: Module::Parties->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: $event::ENTITY_TYPE,
                    entityId: (string) $customer->id,
                    payload: $event::payload($customer),
                );
            }

            return $customer;
        });
    }
}
