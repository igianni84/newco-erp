<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerCreated;
use App\Modules\Parties\Exceptions\ProducerReviewGovernedContentLocked;
use App\Platform\I18n\TranslatableText;
use App\Platform\I18n\TranslatableTextCast;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ProducerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producer — the winery identity registry, the source of the producer reference Module 0's Product Master
 * keys off (parties-core, design D2/D4; party-registry — Requirement: Producer Registry). A STANDALONE
 * entity, NOT a Party subtype (§ 4.4), so it carries no party-type marker.
 *
 * Persistence-only by design (D7): the {@see CreateProducer} action is the sole writer — it inserts the row
 * and records {@see ProducerCreated} in one transaction — so `$guarded = []` carries no
 * mass-assignment-from-request risk. Born `draft`; this change defines no transition out of it (design D2),
 * and no auto-created Supplier (BR-K-Producer-3, design D10). The translatable `description` is held as
 * i18n-keyed JSON via {@see TranslatableTextCast} with per-attribute English fallback.
 *
 * The one model-level guard it DOES carry is the Producer-5 review-governed content lock ({@see booted()}): the
 * review-governed descriptive fields ({@see REVIEW_GOVERNED_FIELDS}) are immutable once the Producer is `active`
 * — a rule invariant on the persisted status, not a state transition (the RM-24 immutability-guard pattern).
 *
 * The `kyc_status` column is the provenance-KYC lifecycle (`not_required → pending → verified | rejected`),
 * distinct from Customer KYC (§ 4.4), added additively as nullable (parties-compliance task 1.2, DEC-071). A
 * NULL `kyc_status` is a Producer never touched by KYC and is treated as CLEARED at the activation gate
 * (design L5), so existing Producers keep activating; the Producer-KYC Actions are its sole writers.
 *
 * @property int $id
 * @property string $name
 * @property string $region
 * @property string|null $appellation
 * @property string $country
 * @property TranslatableText|null $description
 * @property string|null $website
 * @property ProducerStatus $status
 * @property KycStatus|null $kyc_status
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, Club> $clubs
 * @property-read Collection<int, ProducerAgreement> $producerAgreements
 */
class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    protected $table = 'parties_producers';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the catalog spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The review-governed descriptive fields whose edit re-enters the Creator → Reviewer → Approver workflow
     * (canon MVP-DEC-022 / AC-K-BR-Producer-5). Named once here so the {@see booted()} `updating` guard and its
     * regression test share one source (no magic-list drift — the 4.4 `public const` precedent).
     *
     * @var list<string>
     */
    public const REVIEW_GOVERNED_FIELDS = ['name', 'description', 'region', 'website'];

    /**
     * The Clubs this Producer operates — a WITHIN-module `hasMany` (both entities are Module K, so the
     * cross-module relation ban does not apply), the inverse of {@see Club::producer()}. It is the read the
     * retirement cascade walks (design L6): the `RetireProducer` Action (task 3.2) sunsets every `active` Club
     * reachable here. No write surface is exposed — the Club↔Producer link is set once at Club creation and
     * immutable thereafter (BR-K-Club-1/2).
     *
     * @return HasMany<Club, $this>
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class, 'producer_id');
    }

    /**
     * The NewCo↔Producer commercial agreements for this Producer — a WITHIN-module `hasMany` (both entities are
     * Module K), the inverse of {@see ProducerAgreement::producer()}. Surfaced read + create in the operator
     * console's ProducerAgreementsRelationManager (operator-console UI pass, 2026-06-24); the
     * {@see CreateProducerAgreement} action stays the sole writer.
     *
     * @return HasMany<ProducerAgreement, $this>
     */
    public function producerAgreements(): HasMany
    {
        return $this->hasMany(ProducerAgreement::class, 'producer_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Producer::factory()->create()`.
     */
    protected static function newFactory(): ProducerFactory
    {
        return ProducerFactory::new();
    }

    /**
     * BR-K-Producer-5 (canon MVP-DEC-022 / AC-K-BR-Producer-5; change parties-module-k-br-guards, design D9):
     * the review-governed descriptive content ({@see REVIEW_GOVERNED_FIELDS}) is IMMUTABLE while the Producer is
     * `active` — an update dirtying any of it is rejected with the localized {@see ProducerReviewGovernedContentLocked},
     * leaving the row unchanged. This is the INTERIM safety core of the review-freshness rule (unreviewed content
     * never publishes on an active Producer); the full "edit re-arms Creator → Reviewer → Approver" UX is deferred
     * (no Producer `reviewed` state / content-edit path today — RM-06 / RM-14 precedent).
     *
     * Enforced at the `updating` chokepoint — the RM-24 immutability-guard pattern (Catalog's `ProductMaster::booted()`,
     * named in prose to keep this module import-clean) — because the content columns are real + mutable with
     * `$guarded = []` and there is NO Action-layer
     * content-edit writer to guard, so the model event is the only path-complete place that catches EVERY mutation
     * surface. It fires on UPDATE only (creation runs through `creating`, so `draft` content is set freely at
     * birth), keys on `isDirty` of the review-governed set (a status/kyc-only transition — activation, retirement,
     * KYC — dirties none of them, so it passes untouched, the design R5 no-false-positive floor), and gates on the
     * PERSISTED `status` via `getOriginal('status')` (so the lock binds an already-published Producer, and a
     * `draft` — including one being activated in the same write — sets content freely).
     */
    protected static function booted(): void
    {
        static::updating(function (self $producer): void {
            if ($producer->getOriginal('status') === ProducerStatus::Active
                && $producer->isDirty(self::REVIEW_GOVERNED_FIELDS)) {
                throw ProducerReviewGovernedContentLocked::whileActive($producer->id);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => TranslatableTextCast::class,
            'status' => ProducerStatus::class,
            // provenance-KYC lifecycle (parties-compliance task 1.2; design L1/L5) — additive nullable.
            'kyc_status' => KycStatus::class,
            'version' => 'integer',
        ];
    }
}
