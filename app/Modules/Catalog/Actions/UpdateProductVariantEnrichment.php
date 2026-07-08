<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\EnrichmentDataUpdated;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Lifecycle\CatalogContentEdit;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantWineAttributes;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\TranslatableText;

/**
 * Updates a Product Variant's OBSERVATIONAL ENRICHMENT metadata — at launch the tasting notes — through the
 * shared {@see CatalogContentEdit} mechanism's non-versioning `maintain()` entry point
 * (catalog-module-0-completeness-sweep task 4.1; design D2/D11; product-catalog — Requirement: Enrichment Data
 * Update; Module 0 PRD § 14.1 last paragraph, § 9.1, § 13.3 BR-Audit-2; AC-0-EVT-8).
 *
 * Enrichment is not the Variant's identity and it is not review-governed content: it is what the world observes
 * ABOUT the wine, mutable outside the lifecycle. So this Action neither increments `version` nor re-arms review
 * (design D4/D5: `enrichment_updated` ends with none of the four review-freshness suffixes, so the derivation
 * cannot see it), and BR-Audit-2 forbids any pricing or allocation decision from reading it — the deferred
 * Module S marketing consumer (§ 14.5) is the only intended reader of {@see EnrichmentDataUpdated}.
 *
 * The mechanism owns everything shared: ONE transaction, the `lockForUpdate` re-read of the Variant, the
 * `draft`/`reviewed`/`active` state guard (a `retired` Variant must be reopened first — {@see IllegalContentEdit}),
 * the operator-principal floor ({@see ApprovalGovernanceViolation}) and the audit envelope. Both guards run
 * BEFORE the `$apply` closure, so a rejected update diffs nothing and writes nothing — and a `retired` Variant
 * is refused even when the incoming value is identical (the state is the fact the operator must act on, not the
 * no-op).
 *
 * Two effects, and they stand or fall together — the event is recorded from INSIDE the closure, hence inside
 * the mechanism's transaction (§ 14.1 / invariant 4 — the transactional outbox): ONE
 * `catalog.product_variant.enrichment_updated` audit row carrying the before/after of the changed fields, and
 * ONE {@see EnrichmentDataUpdated} domain event with a PII-free payload referencing the Variant by id.
 *
 * IDEMPOTENT by contract: an update carrying the value already stored is a silent NO-OP — no event, no audit
 * row, no write, not even an `updated_at` touch. That is what makes the event meaningful ("the enrichment
 * changed"), and it is why the closure may hand the mechanism `null` for *nothing to record*. The comparison is
 * therefore load-bearing, not cosmetic — it decides whether the event fires — so it is delegated to
 * {@see TranslatableText::sameContent()}, which is order-insensitive over locales and STRICT over texts. It is
 * emphatically not `!=` on the raw maps: loose array comparison would judge `'1e2'` and `'100'` the same text
 * and swallow a real edit. `null` on either side is a legitimate value (untranslated, or cleared).
 *
 * REPLACEMENT semantics, field-agnostic in shape: every enrichment field travels on every call (the console
 * modal prefills them), and only the fields that actually moved reach the write and the audit snapshots (design
 * R9). The launch field set is named in exactly two twin places — {@see incomingEnrichment()} and
 * {@see storedEnrichment()} — so the adapter-fed columns (critic scores, market data) join additively there
 * when the enrichment adapter lands, leaving the diff, the no-op rule, the audit envelope and the event's
 * contract untouched. The fields live on the `WINE` attribute set (§ 16 / AC-0-GEN-3), written through the
 * within-module `wineAttributes()` relation inside the mechanism's transaction (CLAUDE.md invariant 10).
 */
class UpdateProductVariantEnrichment
{
    /** The audit + event entity-type label (the canonical model class name, § 18). */
    private const ENTITY = 'ProductVariant';

    /**
     * The audit verb. Like `whitelist_updated` it ends `_updated` but NOT `.identity_updated`, so the
     * review-freshness derivation is blind to it (design D5's collision discipline) — an observational edit
     * never gates a review, and a reviewed-then-enriched Variant still activates without a re-submit.
     */
    private const VERB = 'enrichment_updated';

    public function __construct(
        private readonly CatalogContentEdit $contentEdit,
        private readonly DomainEventRecorder $eventRecorder,
        private readonly ActorContext $actor,
    ) {}

    /**
     * @param  TranslatableText|null  $tastingNotes  the replacement prose; `null` clears it (no default — omission must not erase content)
     *
     * @throws IllegalContentEdit when the locked Variant is `retired`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductVariant $variant, ?TranslatableText $tastingNotes): ProductVariant
    {
        return $this->contentEdit->maintain(
            $variant,
            self::ENTITY,
            self::VERB,
            fn (ProductVariant $locked): ?array => $this->applyEnrichment($locked, $tastingNotes),
        );
    }

    /**
     * Diff the enrichment fields against the LOCKED Variant's attribute set; on a real change write them, record
     * the domain event in the mechanism's transaction, and hand back the (empty) core-column change plus the
     * before/after snapshots. On no change at all, report `null` — the mechanism then writes nothing.
     *
     * @return array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}|null
     */
    private function applyEnrichment(ProductVariant $locked, ?TranslatableText $tastingNotes): ?array
    {
        // The 1:1 attribute set of the locked Variant. Every `WINE` Variant has one (the creation Action and the
        // factory both write it in the same transaction as the core row), so its absence is a structural fault,
        // not a domain rejection — `firstOrFail()` says exactly that.
        $wine = $locked->wineAttributes()->firstOrFail();

        $stored = $this->storedEnrichment($wine);

        /** @var array<string, mixed> $columns */
        $columns = [];
        /** @var array<string, mixed> $before */
        $before = [];
        /** @var array<string, mixed> $after */
        $after = [];

        foreach ($this->incomingEnrichment($tastingNotes) as $field => $value) {
            // Prose compares by CONTENT, never by object identity and never with `!=` on the raw maps: loose
            // array comparison recurses into loose value comparison, under which two numeric strings compare
            // NUMERICALLY ('1e2' == '100'). {@see TranslatableText::sameContent()} owns the correct equality —
            // order-insensitive over locales, strict over texts, absence ≡ empty map. Both maps still ride into
            // the snapshots verbatim, `null` (untranslated / cleared) included.
            $storedValue = $stored[$field] ?? null;

            if (! TranslatableText::sameContent($storedValue, $value)) {
                $columns[$field] = $value;
                $before[$field] = $storedValue?->jsonSerialize();
                $after[$field] = $value?->jsonSerialize();
            }
        }

        // Nothing moved: the idempotent no-op (design D11 / AC-0-EVT-8). No event, no audit row, no write — the
        // mechanism returns the Variant untouched, and a consumer of `EnrichmentDataUpdated` can trust that every
        // event it receives corresponds to a real change.
        if ($columns === []) {
            return null;
        }

        // The per-type attribute set is a related row: it is written HERE, inside the mechanism's transaction, so
        // it commits with the audit row and the event — or with none of them.
        $wine->update($columns);

        $this->eventRecorder->record(
            name: EnrichmentDataUpdated::NAME,
            module: Module::Catalog->value,
            actorRole: $this->actor->role(),
            actorId: $this->actor->actorId(),
            entityType: EnrichmentDataUpdated::ENTITY_TYPE,
            entityId: (string) $locked->id,
            payload: EnrichmentDataUpdated::payload($locked),
        );

        // The enrichment lives wholly on the attribute row, so the Variant's own columns never change — the
        // mechanism issues no `UPDATE` on it at all, and its `version` and `updated_at` both stand.
        return ['attributes' => [], 'before' => $before, 'after' => $after];
    }

    /**
     * The REPLACEMENT enrichment, as a field ⇒ value map. One of the two twin places naming the launch field set
     * (§ 9.1: the variant-level tasting notes; the critic scores and market data arrive with the enrichment
     * adapter, which is out of this change's scope).
     *
     * @return array<string, TranslatableText|null>
     */
    private function incomingEnrichment(?TranslatableText $tastingNotes): array
    {
        return ['tasting_notes' => $tastingNotes];
    }

    /**
     * The STORED enrichment of the locked Variant's `WINE` attribute set, in the same field ⇒ value shape — the
     * twin of {@see incomingEnrichment()}. Read through the model's typed `@property` accessors rather than a
     * generic `getAttribute()`, so the cast's `TranslatableText|null` is what the diff actually sees.
     *
     * @return array<string, TranslatableText|null>
     */
    private function storedEnrichment(ProductVariantWineAttributes $wine): array
    {
        return ['tasting_notes' => $wine->tasting_notes];
    }
}
