<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Lifecycle\CatalogContentEdit;
use App\Modules\Catalog\Models\ProductMaster;
use App\Platform\I18n\TranslatableText;

/**
 * Edits a Product Master's review-governed IDENTITY CONTENT in place — product name, appellation, region and
 * the winery-story prose — through the shared {@see CatalogContentEdit} mechanism
 * (catalog-module-0-completeness-sweep task 2.1; design D1/D2; product-catalog — Requirement: Identity Edit
 * and Re-Versioning; Module 0 PRD § 4.8 + § 13.3 BR-Audit-1, § 5.5, DEC-073).
 *
 * The mechanism owns everything shared: ONE transaction, the `lockForUpdate` re-read, the
 * `draft`/`reviewed`/`active` state guard (a `retired` Master must be reopened first —
 * {@see IllegalContentEdit}), the operator-principal floor ({@see ApprovalGovernanceViolation}), the in-place
 * `version` increment written in the SAME `UPDATE` as the field writes, the single
 * `catalog.product_master.identity_updated` audit row carrying the before/after of the CHANGED fields, and the
 * absence of any domain event. This Action supplies only the field semantics, as the `$apply` closure the
 * mechanism invokes AFTER both guards pass, against the LOCKED row — so a rejected edit never reaches the
 * dedup query and writes nothing.
 *
 * REPLACEMENT semantics, not a patch: all four identity fields are passed on every call (the console modal
 * prefills them from the current row), and the closure diffs them against the locked truth. Only the fields
 * that actually changed reach the `UPDATE`s and the audit snapshots (design R9 — minimal before/after, so the
 * PII/redaction posture mirrors the transition rows). A `null` `$wineryStory` therefore CLEARS the prose; the
 * parameter carries no default so a caller cannot erase it by omission.
 *
 * BR-Identity-1 is re-checked on edit (§ 13.1): when the product name or the appellation changes, the
 * type-defined identity key `producer + product name + appellation` is re-verified against every OTHER
 * non-retired Master — the same plain-column join `CreateProductMaster` runs at creation, plus a
 * `id != <this master>` exclusion so a Master never collides with itself. A collision throws
 * {@see DuplicateProductMasterIdentity} from INSIDE the mechanism's transaction, so the values, the `version`,
 * the audit trail and the event log are all left unchanged. An edit that touches only the region or the winery
 * story leaves the identity key untouched and runs no dedup query at all.
 *
 * The verb `identity_updated` participates in review freshness (design D4/D5): editing this content while the
 * Master sits in `reviewed` RE-ARMS its review — activation is blocked until an explicit re-submit (the DEC-019
 * edit leg). An edit on an `active` Master leaves it `active` (the FSM has no `active → reviewed` edge): the
 * version increment and the audited before/after are the control on that operator-correction path. A `draft`
 * edit is cleared by the subsequent submit, whose `.submitted` row becomes the latest relevant action.
 *
 * The producer is never re-bound here (a Master's producer is fixed at creation, like its `product_type`), and
 * the wine attribute set is written through the within-module `wineAttributes()` relation — no Eloquent
 * relation crosses the module boundary (CLAUDE.md invariant 10).
 */
class UpdateProductMasterIdentity
{
    /**
     * The audit verb (design D5). It ENDS with one of the four review-freshness-relevant suffixes deliberately:
     * review-governed content changed, so the Master is review-stale until it is explicitly re-submitted.
     */
    private const VERB = 'identity_updated';

    public function __construct(private readonly CatalogContentEdit $contentEdit) {}

    /**
     * @param  TranslatableText|null  $wineryStory  the replacement prose; `null` clears it (no default — omission must not erase content)
     *
     * @throws IllegalContentEdit when the locked Master is `retired`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     * @throws DuplicateProductMasterIdentity when the new name/appellation collides with another non-retired Master
     */
    public function handle(
        ProductMaster $master,
        string $name,
        string $appellation,
        string $region,
        ?TranslatableText $wineryStory,
    ): ProductMaster {
        return $this->contentEdit->edit(
            $master,
            'ProductMaster',
            self::VERB,
            fn (ProductMaster $locked): array => $this->applyIdentity($locked, $name, $appellation, $region, $wineryStory),
        );
    }

    /**
     * Diff the four identity fields against the LOCKED row, re-check BR-Identity-1 when the identity key moves,
     * write the wine attribute set's changed columns, and hand the mechanism the Master's own changed columns
     * plus the before/after snapshots of everything that changed.
     *
     * @return array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}
     */
    private function applyIdentity(
        ProductMaster $locked,
        string $name,
        string $appellation,
        string $region,
        ?TranslatableText $wineryStory,
    ): array {
        // The 1:1 attribute set of the locked Master. A `WINE` Master always has one (the creation Action and the
        // factory both write it in the same transaction as the core row), so its absence is a structural fault,
        // not a domain rejection — `firstOrFail()` says exactly that.
        $wine = $locked->wineAttributes()->firstOrFail();

        // The identity key moved ⇒ re-check the dedup. A region-or-story-only edit leaves `producer + name +
        // appellation` untouched, so no other Master's key can be reached and the join is never run.
        if ($name !== $locked->name || $appellation !== $wine->appellation) {
            $this->assertIdentityIsUnique($locked, $name, $appellation);
        }

        /** @var array<string, mixed> $coreAttributes */
        $coreAttributes = [];
        /** @var array<string, mixed> $wineAttributes */
        $wineAttributes = [];
        /** @var array<string, mixed> $before */
        $before = [];
        /** @var array<string, mixed> $after */
        $after = [];

        if ($name !== $locked->name) {
            $coreAttributes['name'] = $name;
            $before['name'] = $locked->name;
            $after['name'] = $name;
        }

        if ($appellation !== $wine->appellation) {
            $wineAttributes['appellation'] = $appellation;
            $before['appellation'] = $wine->appellation;
            $after['appellation'] = $appellation;
        }

        if ($region !== $wine->region) {
            $wineAttributes['region'] = $region;
            $before['region'] = $wine->region;
            $after['region'] = $region;
        }

        // The prose compares by CONTENT, never by object identity and never with `!=` on the raw maps: loose array
        // comparison recurses into loose value comparison, under which two numeric strings compare NUMERICALLY
        // ('1e2' == '100'), swallowing a real edit. {@see TranslatableText::sameContent()} owns the correct
        // equality — order-insensitive over locales, strict over texts. `null` on either side is a legitimate
        // value — an untranslated, or a cleared, story — and is preserved verbatim in the snapshots.
        if (! TranslatableText::sameContent($wine->winery_story, $wineryStory)) {
            $wineAttributes['winery_story'] = $wineryStory;
            $before['winery_story'] = $wine->winery_story?->jsonSerialize();
            $after['winery_story'] = $wineryStory?->jsonSerialize();
        }

        // The per-type attribute set is a related row: it is written HERE, inside the mechanism's transaction, so
        // it commits with the core columns, the `version` increment and the audit row — or with none of them.
        if ($wineAttributes !== []) {
            $wine->update($wineAttributes);
        }

        return ['attributes' => $coreAttributes, 'before' => $before, 'after' => $after];
    }

    /**
     * BR-Identity-1 on the edit path: the `producer + product name + appellation` key must be unique among
     * non-retired Masters. The same plain-column join `CreateProductMaster` runs (portable on both engines —
     * `appellation` is a real column, ADR decisions/2026-06-14-catalog-category-neutral-representation.md),
     * narrowed to the spec's "every OTHER non-retired Master" by `whereKeyNot`.
     *
     * That self-exclusion is defence-in-depth, not a live branch: the caller above reaches this method only when
     * the key MOVED, and a moved key can never equal the Master's own current key. It is written anyway because
     * the delta spec states the rule as "every other Master", and because an unconditional caller (a future
     * surface that re-checks on every save) would otherwise make a Master collide with itself.
     *
     * @throws DuplicateProductMasterIdentity
     */
    private function assertIdentityIsUnique(ProductMaster $locked, string $name, string $appellation): void
    {
        $collides = ProductMaster::query()
            ->join('catalog_product_master_wine_attributes as wine', 'wine.product_master_id', '=', 'catalog_product_masters.id')
            ->whereKeyNot($locked->getKey())
            ->where('catalog_product_masters.producer_id', $locked->producer_id)
            ->where('catalog_product_masters.name', $name)
            ->where('wine.appellation', $appellation)
            ->where('catalog_product_masters.lifecycle_state', '!=', LifecycleState::Retired->value)
            ->exists();

        if ($collides) {
            throw DuplicateProductMasterIdentity::forWine($locked->producer_id, $name, $appellation);
        }
    }
}
