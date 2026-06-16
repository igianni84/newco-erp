<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubCreated;
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
 * (a Club MAY carry no fee). `registration_flow_type` is a fixed per-Club classifier; `generates_credit` /
 * `invite_only` are the single-tier-at-launch flags (DEC-062). This change defines no transition out of
 * `active` (design D2).
 *
 * @property int $id
 * @property string $display_name
 * @property int $producer_id
 * @property ClubStatus $status
 * @property Money|null $fee
 * @property ClubRegistrationFlowType $registration_flow_type
 * @property bool $generates_credit
 * @property bool $invite_only
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'fee' => MoneyCast::class,
            'registration_flow_type' => ClubRegistrationFlowType::class,
            'generates_credit' => 'boolean',
            'invite_only' => 'boolean',
            'version' => 'integer',
        ];
    }
}
