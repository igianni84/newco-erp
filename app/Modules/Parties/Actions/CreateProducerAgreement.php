<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\SettlementCadence;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use App\Modules\Parties\Exceptions\InvalidSettlementCadence;
use App\Modules\Parties\Exceptions\MissingAgreementProducer;
use App\Modules\Parties\Exceptions\ProducerAgreementClubNotActive;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a ProducerAgreement in `draft` for an existing Producer (optionally narrowed to one Club) and records
 * its {@see ProducerAgreementCreated} event atomically (parties-core, design D3/D4/D7; party-registry —
 * Requirement: ProducerAgreement, Spine Creation Events).
 *
 * Three guards make the launch invariants enforced, not advised:
 *   - REQUIRED PRODUCER (§ 4.6): a ProducerAgreement references EXACTLY ONE Producer. Inside the transaction a
 *     presence check rejects a `producer_id` that matches no Producer with a localized
 *     {@see MissingAgreementProducer} reason. The within-module FK on `parties_producer_agreements.producer_id`
 *     is the true structural guard; the pre-check surfaces a clean operator reason ahead of the raw integrity
 *     error.
 *   - SETTLEMENT-CADENCE CLOSED SET (§ 4.6 / BR-K-Agreement-2, canon MVP-DEC-010, RM-22): the free-text cadence
 *     operand is resolved against the closed {@see SettlementCadence} set at the action boundary. A null operand
 *     stays null (the cadence is optional — the column is nullable); a non-null out-of-set/typo token is rejected
 *     with a localized {@see InvalidSettlementCadence} BEFORE the write — ahead of the raw ValueError the model's
 *     enum cast would throw on create(). Enforced server-side (not UI-only) because the cadence times Module-E
 *     settlement and Module-D PO issuance; the PostgreSQL CHECK is the DB backstop, the cast the SQLite floor.
 *   - CLUB-ACTIVE SCOPING (§ 4.6 / BR-K-Agreement-4, canon MVP-DEC-009): a per-Club-narrowed agreement's Club
 *     MUST be `active` at the time of scoping. Inside the transaction, a `clubId` whose Club is `sunset` or
 *     `closed` is rejected with a localized {@see ProducerAgreementClubNotActive} reason BEFORE the write — no
 *     agreement, no event. Producer-wide scope (`clubId` NULL) is ungated, and a non-existent Club falls through
 *     to the within-module FK backstop (only a real, non-active Club trips the guard). Supersession/renewal
 *     INHERITS the superseded scope and is EXEMPT, but that path runs through activation (task 3.3), not this
 *     creation Action, so no exemption branch is needed here.
 *
 * The Club narrowing is OPTIONAL — a null `clubId` is a Producer-wide agreement; a value scopes it to that one
 * Club, which MUST be `active` (the CLUB-ACTIVE SCOPING guard above; the FK is the structural backstop for a
 * non-existent Club). The "at most one ACTIVE agreement per
 * Producer scope" rule (BR-K-Agreement-1) is an ACTIVATION-time invariant and is therefore NOT enforced here:
 * draft agreements are created freely (design D2, the spec's creation-only scope). Then, in ONE
 * {@see DB::transaction}: insert the agreement (born `draft`) and record the PII-free event via the platform
 * {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until real
 * principals wire in). The recorder's own transaction guard makes write + emit atomic. The model stays
 * persistence-only; this action is the seam the deferred lifecycle change extends.
 */
class CreateProducerAgreement
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        int $producerId,
        ?int $clubId = null,
        ?CarbonInterface $termStart = null,
        ?CarbonInterface $termEnd = null,
        ?string $settlementCadence = null,
    ): ProducerAgreement {
        // RM-22 (BR-K-Agreement-2 / canon MVP-DEC-010): resolve the free-text settlement-cadence operand against
        // the closed SettlementCadence set at the action boundary — a null operand stays null (cadence is optional,
        // the column is nullable), a non-null out-of-set/typo token is rejected with a localized reason BEFORE the
        // write (ahead of the raw ValueError the enum cast would throw on create()). Pure input validation, so it
        // fails fast outside the transaction — the resolved enum is what the insert persists.
        $cadence = $settlementCadence === null
            ? null
            : (SettlementCadence::tryFrom($settlementCadence) ?? throw InvalidSettlementCadence::forCadence($settlementCadence));

        return DB::transaction(function () use (
            $producerId,
            $clubId,
            $termStart,
            $termEnd,
            $cadence,
        ): ProducerAgreement {
            // § 4.6: a ProducerAgreement requires an EXISTING Producer. Reject a missing/non-existent reference
            // with a clean localized reason (the FK is the structural backstop). The single-active-per-scope
            // rule is an activation-time invariant — NOT checked here; drafts create freely.
            if (! Producer::query()->whereKey($producerId)->exists()) {
                throw MissingAgreementProducer::forId($producerId);
            }

            // BR-K-Agreement-4 (canon MVP-DEC-009): a per-Club-narrowed agreement's Club MUST be `active` at the
            // time of scoping. A `clubId` whose Club is `sunset`/`closed` is rejected with a localized reason, so
            // no agreement and no event are recorded. Producer-wide scope (clubId NULL) is ungated; a non-existent
            // Club falls through to the within-module FK backstop — only a real, non-active Club trips this guard.
            // Supersession/renewal inherits the superseded scope and is EXEMPT, but that runs through the
            // activation path (task 3.3), never this creation Action, so no exemption branch is needed here.
            if ($clubId !== null) {
                $club = Club::query()->whereKey($clubId)->first();

                if ($club !== null && $club->status !== ClubStatus::Active) {
                    throw ProducerAgreementClubNotActive::forClub($clubId, $club->status->value);
                }
            }

            $agreement = ProducerAgreement::create([
                'producer_id' => $producerId,
                'club_id' => $clubId,
                'status' => ProducerAgreementStatus::Draft,
                'term_start' => $termStart,
                'term_end' => $termEnd,
                'settlement_cadence' => $cadence,
            ]);

            $this->recorder->record(
                name: ProducerAgreementCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerAgreementCreated::ENTITY_TYPE,
                entityId: (string) $agreement->id,
                payload: ProducerAgreementCreated::payload($agreement),
            );

            return $agreement;
        });
    }
}
