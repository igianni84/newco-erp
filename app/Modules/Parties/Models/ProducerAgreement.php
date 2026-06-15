<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ProducerAgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProducerAgreement — the NewCo↔Producer commercial agreement (parties-core, design D2/D4; party-registry —
 * Requirement: ProducerAgreement). A NewCo net-new entity (DEC-070, § 4.6): it references EXACTLY ONE Producer
 * (required) and MAY be narrowed to a specific Club (optional). Both relations are WITHIN-module `belongsTo`
 * (the boundary law forbids only CROSS-module relations — Producer and Club are in the same module): the
 * {@see producer()} link is required (a non-nullable FK), the {@see club()} narrowing is optional (a nullable
 * FK — null is a Producer-wide agreement).
 *
 * Persistence-only by design (D7): the {@see CreateProducerAgreement} action is the sole writer — it inserts
 * the row (born `draft`) and records {@see ProducerAgreementCreated} in one transaction — so `$guarded = []`
 * carries no mass-assignment-from-request risk. `term_start` / `term_end` are the (nullable) agreement term
 * dates; `settlement_cadence` is the nullable D19 settlement-cadence seam Module E reads. This change defines
 * no transition out of `draft` (design D2), and the "at most one active agreement per Producer scope" rule
 * (BR-K-Agreement-1) is an activation-time invariant, out of this creation-only slice.
 *
 * @property int $id
 * @property int $producer_id
 * @property int|null $club_id
 * @property ProducerAgreementStatus $status
 * @property CarbonImmutable|null $term_start
 * @property CarbonImmutable|null $term_end
 * @property string|null $settlement_cadence
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Producer $producer
 * @property-read Club|null $club
 */
class ProducerAgreement extends Model
{
    /** @use HasFactory<ProducerAgreementFactory> */
    use HasFactory;

    protected $table = 'parties_producer_agreements';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the Club and Producer spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The required Producer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module
     * relation ban does not apply). The reference is required (a non-nullable FK); there is no inverse
     * `hasMany` exposed in this slice.
     *
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class, 'producer_id');
    }

    /**
     * The optional narrowing Club — a WITHIN-module `belongsTo`. The reference is OPTIONAL (a nullable FK): a
     * null `club_id` is a Producer-wide agreement, a value scopes it to that one Club (§ 4.6).
     *
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `ProducerAgreement::factory()->create()`.
     */
    protected static function newFactory(): ProducerAgreementFactory
    {
        return ProducerAgreementFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProducerAgreementStatus::class,
            'term_start' => 'immutable_date',
            'term_end' => 'immutable_date',
            'version' => 'integer',
        ];
    }
}
