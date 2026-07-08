<?php

use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Layer-1 whitelist SUBSTRATE — the `catalog_variant_case_whitelists` table and its
 * {@see VariantCaseWhitelistEntry} model (catalog-module-0-completeness-sweep task 1.1; design D6;
 * product-catalog — Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 3.3 + § 7.1).
 *
 * Two halves, both from AC-0-XM-11's scenario "Layer 1 exposes no breakability flag":
 *
 *   PRESENCE — the structure keys ONLY Product Variant / Format / Case Configuration, with FK integrity
 *   (cascade from the owning Variant; RESTRICT from the two shared reference entities) and a uniqueness
 *   constraint on the TRIPLE (a CC is admitted at most once per pair; the same CC recurs freely across
 *   different pairs — the per-(Variant, Format) scoping this table exists to express).
 *
 *   ABSENCE — no `is_breakable`/`breakable`/equivalent field exists on it. Layer 1 catalogs POSSIBILITY;
 *   the effective breakability rule is computed downstream (Module A Layer 2 / Module S Layer 3). The
 *   absence IS the contract, so it is asserted, not assumed — mirroring `CaseConfigurationTest`'s
 *   BR-RefData-2 guard, which this test extends to the second (and last) PIM surface that could have
 *   carried the flag.
 *
 * No maintenance Action exists yet (task 3.1) and no activation gate reads the table yet (task 3.2), so the
 * rows here are written straight through the model as fixtures — exactly what a factory would do.
 *
 * RefreshDatabase: nothing here tests a level-0 guard, an `afterCommit` hook, or genuine commit/rollback
 * (knowledge/testing/rules.md #5). Every constraint violation is raised inside a nested `DB::transaction()`
 * savepoint (trap 5) so PostgreSQL's aborted-transaction state rolls back to it and the follow-on
 * row-state assertions run in a live transaction — SQLite needs no such care, PG does.
 */
uses(RefreshDatabase::class);

/**
 * Persist one admitted triple. Named uniquely per file (Pest top-level functions share one global
 * namespace — knowledge/testing/rules.md).
 */
function vcwEntry(ProductVariant $variant, Format $format, CaseConfiguration $caseConfiguration): VariantCaseWhitelistEntry
{
    return VariantCaseWhitelistEntry::create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'case_configuration_id' => $caseConfiguration->id,
    ]);
}

it('persists an admitted (Variant, Format, Case Configuration) triple with its three references cast to integers', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    $entry = vcwEntry($variant, $format, $caseConfiguration);

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = VariantCaseWhitelistEntry::findOrFail($entry->id);

    expect($read->product_variant_id)->toBe($variant->id)
        ->and($read->format_id)->toBe($format->id)
        ->and($read->case_configuration_id)->toBe($caseConfiguration->id)
        ->and($read->created_at)->not->toBeNull()
        ->and($read->updated_at)->not->toBeNull();
});

it('exposes the Variant\'s whitelist entries through a within-module relation', function () {
    $variant = ProductVariant::factory()->create();
    $otherVariant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $owc = CaseConfiguration::factory()->create();
    $carton = CaseConfiguration::factory()->create();

    vcwEntry($variant, $format, $owc);
    vcwEntry($variant, $format, $carton);
    vcwEntry($otherVariant, $format, $owc); // another Variant's statement — never this one's

    expect($variant->caseWhitelistEntries()->pluck('case_configuration_id')->sort()->values()->all())
        ->toBe(collect([$owc->id, $carton->id])->sort()->values()->all())
        ->and($otherVariant->caseWhitelistEntries)->toHaveCount(1);
});

it('rejects a duplicate triple at the DB unique index — a Case Configuration is admitted at most once per pair', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    vcwEntry($variant, $format, $caseConfiguration);

    // The admitted set is a SET, not a multiset. Enforced on BOTH engines by the unique index; the nested
    // transaction is the savepoint (trap 5) so PG's abort rolls back to it and the assertions below survive.
    expect(fn () => DB::transaction(fn () => vcwEntry($variant, $format, $caseConfiguration)))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(1);
});

it('scopes uniqueness to the whole triple — the same Case Configuration recurs across other pairs', function () {
    $variantA = ProductVariant::factory()->create();
    $variantB = ProductVariant::factory()->create();
    $formatA = Format::factory()->create();
    $formatB = Format::factory()->create();
    $owc = CaseConfiguration::factory()->create();

    // The SAME Case Configuration admitted for three DIFFERENT (Variant, Format) pairs — the per-pair
    // scoping the table exists to express (§ 3.3: "this product, IN THIS FORMAT"). None collides.
    vcwEntry($variantA, $formatA, $owc);
    vcwEntry($variantA, $formatB, $owc); // same Variant, other Format
    vcwEntry($variantB, $formatA, $owc); // other Variant, same Format

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(3)
        ->and($variantA->caseWhitelistEntries)->toHaveCount(2)
        ->and($variantA->caseWhitelistEntries()->where('format_id', $formatA->id)->count())->toBe(1);
});

it('restricts deleting a Format or a Case Configuration that a whitelist entry names', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    vcwEntry($variant, $format, $caseConfiguration);

    // Both are standalone SHARED reference entities: neither may be deleted out from under a whitelist that
    // names it. RESTRICT is the framework default (no `onDelete` clause) and is enforced on both engines —
    // SQLite honours it because `foreign_key_constraints` is on (config/database.php).
    expect(fn () => DB::transaction(fn () => $format->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $caseConfiguration->delete()))->toThrow(QueryException::class);

    // Neither the parents nor the entry moved.
    expect(VariantCaseWhitelistEntry::query()->count())->toBe(1)
        ->and(Format::query()->whereKey($format->id)->exists())->toBeTrue()
        ->and(CaseConfiguration::query()->whereKey($caseConfiguration->id)->exists())->toBeTrue();
});

it('cascades whitelist entries away with the Variant that owns them', function () {
    $variant = ProductVariant::factory()->create();
    $survivor = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    vcwEntry($variant, $format, $caseConfiguration);
    vcwEntry($survivor, $format, $caseConfiguration);

    // A whitelist entry is a statement ABOUT its Variant and has no meaning without it — the ownership
    // asymmetry the migration encodes (cascade from the Variant, restrict from the reference entities).
    $variant->delete();

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(1)
        ->and(VariantCaseWhitelistEntry::query()->sole()->product_variant_id)->toBe($survivor->id);
});

it('carries no breakability attribute or column — Layer 1 catalogs possibility only (AC-0-XM-11)', function () {
    // BR-RefData-2 / § 7.4 / AC-0-XM-11: no module reads an `is_breakable` flag from PIM because PIM exposes
    // none. The Case Configuration was already proven flag-free (CaseConfigurationTest); the whitelist is the
    // only other PIM surface that could plausibly have carried one, so the absence is asserted here too.
    expect(Schema::hasColumn('catalog_variant_case_whitelists', 'is_breakable'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_variant_case_whitelists', 'breakable'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_variant_case_whitelists', 'breakability'))->toBeFalse();

    $columns = Schema::getColumnListing('catalog_variant_case_whitelists');

    foreach ($columns as $column) {
        expect($column)->not->toContain('break');
    }

    // The structure keys ONLY the three references (+ surrogate id and audit timestamps): no boolean, no
    // lifecycle_state, no version — the whitelist rides its Variant's lifecycle and never versions it (D6).
    expect($columns)->toEqualCanonicalizing([
        'id',
        'product_variant_id',
        'format_id',
        'case_configuration_id',
        'created_at',
        'updated_at',
    ]);
});
