<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Exceptions\ClubRegistrationFlowNotSelectable;
use App\Platform\Money\Money;
use App\Platform\Money\MoneyCast;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ClubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Club — a Producer-operated membership program (parties-core, design D2/D4; party-registry — Requirement:
 * Club). It is associated with EXACTLY ONE operating Producer (§ 4.3, BR-K-Club-1) and that link is
 * immutable once set (BR-K-Club-2): the {@see producer()} relation is a WITHIN-module `belongsTo` (the
 * boundary law forbids only CROSS-module relations — Producer is in the same module), and the {@see CreateClub}
 * action exposes no operation that reassigns `producer_id`.
 *
 * Persistence-only by design (D7): the {@see CreateClub} action is the sole writer — it inserts the row (born
 * `active`) and records {@see ClubCreated} in one transaction — so `$guarded = []` carries no
 * mass-assignment-from-request risk. The per-Club `fee` is held as {@see Money} via {@see MoneyCast} (integer
 * minor units + ISO 4217 code across `fee_minor`/`fee_currency`, never a float — invariant 6); it is nullable
 * (a Club MAY carry no fee). `registration_flow_type` is a fixed per-Club classifier; `generates_credit` is
 * the single-tier-at-launch flag (DEC-062). `auto_renew_default` is the Club-level
 * auto-renewal default a new Profile inherits at creation (Profile-5, parties-module-k-br-guards task 2.2) —
 * the standalone `auto_renew` element of the deferred `renewal_policy` blob (MVP-DEC-013), born `true`. This
 * change defines no transition out of `active` (design D2). The one model-level guard it DOES carry is the
 * Club-6 latent-value reject ({@see booted()}): `open_registration` is a non-selectable-at-launch flow, rejected
 * on every write path — a value invariant, not a state transition.
 *
 * @property int $id
 * @property string $display_name
 * @property int $producer_id
 * @property ClubStatus $status
 * @property Money|null $fee
 * @property ClubRegistrationFlowType $registration_flow_type
 * @property bool $generates_credit
 * @property bool $auto_renew_default
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Producer $producer
 */
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $table = 'parties_clubs';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the catalog and Producer spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The operating Producer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module
     * relation ban does not apply). The link is required (a non-nullable FK) and immutable once set
     * (BR-K-Club-1/2). Its inverse is {@see Producer::clubs()}, the `hasMany` the retirement cascade walks
     * (parties-producer-lifecycle, design L6).
     *
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class, 'producer_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Club::factory()->create()`.
     */
    protected static function newFactory(): ClubFactory
    {
        return ClubFactory::new();
    }

    /**
     * Club-6 (canon MVP-DEC-022 / AC-K-BR-Club-6; change parties-module-k-br-guards, design D6): the
     * `open_registration` value (auto-join without approval) is CARRIED LATENT in {@see ClubRegistrationFlowType}
     * but SHALL NOT be selectable at launch — it would contradict the mandatory producer-approval write
     * (DEC-069: approval = charge = activation is mandatory for every flow; no value auto-approves). This
     * `saving` guard rejects persisting it on EVERY write path — create OR update (the spec's literal scope) —
     * so the value invariant holds beneath the {@see CreateClub} action, the factory, the seeder, and any future
     * update writer, ahead of any NOT-NULL/enum backstop, with the localized {@see ClubRegistrationFlowNotSelectable}
     * reason. The read goes through the enum cast, so a value set as either the enum or its backing string is caught.
     * The three launch-selectable channels are `application_with_approval` (the default), `invitation_only`, and
     * `link_onboarding`.
     */
    protected static function booted(): void
    {
        static::saving(function (Club $club): void {
            if ($club->registration_flow_type === ClubRegistrationFlowType::OpenRegistration) {
                throw ClubRegistrationFlowNotSelectable::forFlow($club->registration_flow_type->value);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'fee' => MoneyCast::class,
            'registration_flow_type' => ClubRegistrationFlowType::class,
            'generates_credit' => 'boolean',
            // the Club-level auto-renew default a new Profile inherits at creation (Profile-5;
            // parties-module-k-br-guards task 2.2) — additive NOT-NULL boolean, DB-defaulted `true`.
            'auto_renew_default' => 'boolean',
            'version' => 'integer',
        ];
    }
}
