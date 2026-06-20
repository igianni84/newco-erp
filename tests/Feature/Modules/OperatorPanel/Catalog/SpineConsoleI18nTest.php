<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 5.1 (operator-console-catalog-spine; design L7; spec — the capability's existing i18n requirement
// governs the new surfaces; invariant 12 — no hardcoded user-facing strings). The six spine consoles —
// Format, Case Configuration, Product Variant, Product Reference, Sellable SKU, Composite SKU — shipped in
// tasks 2.1–4.1, each routing its copy through the `operator_console.<entity>` translation group and reusing
// the shared kit (OperatorConsoleResource / OperatorConsoleViewRecord / SurfacesDomainActions). The kit
// resolves a UNIFORM set of keys per entity off each entity's i18nKey() — the model labels, the two lifecycle
// columns, the five lifecycle action labels + their success notifications + the action-failed title, the
// reject-notes field and the second-actor affordance — by string concatenation in the base classes, so those
// keys never appear as literals in the per-entity source. A scan of the per-entity files therefore cannot
// prove they are authored; only enumerating the kit contract can (the high-value guard below).
//
// This is the spine's capability-close i18n guard, mirroring the predecessor's ProductMasterConsoleI18nTest:
//   (1) EN baseline completeness — every key the six consoles resolve (the kit's uniform set + each entity's
//       own literal columns/fields/values + the Product Reference duplicate message) is authored in lang/en,
//       so no label renders as a raw key under any locale;
//   (2) Italian rendering — representative keys across all six resolve to authored Italian under `it`;
//   (3) per-key EN fallback — each entity's English-invariant `label`/`plural_label` is omitted from `it` yet
//       still resolves to its English baseline value, not the raw key (DEC-127);
//   (4) IT ⊆ EN — every authored Italian spine key has an English counterpart (no dangling fallback); and
//   (5) the sink-anchored token scan (reused from the predecessor) finds no hardcoded user-facing literal in
//       the six consoles' Filament classes (invariant 12).
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase. Domain rejection
// bodies (a Catalog action's exception message surfaced as a notification body) come from lang/en/catalog.php,
// which is EN-only in this repo; under `it` they fall back per-key to EN (DEC-127). Authoring the IT Catalog
// group is the Catalog module's own i18n concern, out of this operator-surface change's scope.

/**
 * The six spine console entities — their `operator_console.<entity>` i18n roots.
 *
 * @return list<string>
 */
function spineConsoleEntities(): array
{
    return ['format', 'case_configuration', 'product_variant', 'product_reference', 'sellable_sku', 'composite_sku'];
}

/**
 * The uniform copy keys the shared console kit resolves for EVERY spine entity off its i18nKey(), by string
 * concatenation in the base classes (so they are invisible to a per-entity source scan):
 *   - OperatorConsoleResource: label, plural_label, columns.lifecycle_state, columns.version;
 *   - OperatorConsoleViewRecord: the five action labels, fields.rejection_notes (reject form), affordance.second_actor;
 *   - SurfacesDomainActions: the five success notifications + notifications.action_failed.
 * A console that extends the kit MUST author all of these or a label renders as a raw key under any locale.
 *
 * @return list<string>
 */
function spineConsoleKitKeys(): array
{
    return [
        'label', 'plural_label',
        'columns.lifecycle_state', 'columns.version',
        'actions.submit', 'actions.reject', 'actions.activate', 'actions.retire', 'actions.reopen',
        'fields.rejection_notes', 'affordance.second_actor',
        'notifications.submitted', 'notifications.rejected', 'notifications.activated',
        'notifications.retired', 'notifications.reopened', 'notifications.action_failed',
    ];
}

/**
 * Every `<entity>.<path>` translation key referenced as a literal `operator_console.…` string in the six spine
 * consoles' Filament source — the per-entity columns/fields/values, the list-header create action and the
 * Product Reference duplicate message that the kit does NOT resolve generically. The Product Master surface
 * (the predecessor's, scanned by ProductMasterConsoleI18nTest) is excluded so this guard travels with the six.
 *
 * @return list<string> dot-paths WITHOUT the `operator_console.` prefix (e.g. `format.columns.name`)
 */
function spineConsoleReferencedKeys(): array
{
    $entities = implode('|', spineConsoleEntities());

    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Catalog')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && ! str_contains($file->getPathname(), 'ProductMaster'),
    );

    /** @var array<string, true> $keys */
    $keys = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        if (preg_match_all('/operator_console\.(('.$entities.')\.[a-z0-9_.]+)/', $source, $matches) > 0) {
            foreach ($matches[1] as $path) {
                $keys[$path] = true;
            }
        }
    }

    return array_keys($keys);
}

it('authors every kit-resolved console key in the English baseline for each spine entity', function (string $entity) {
    // The kit's uniform keys are concatenated in the base classes, never literal in the per-entity files, so a
    // source scan can't see them and the lifecycle tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    foreach (spineConsoleKitKeys() as $suffix) {
        $key = "operator_console.{$entity}.{$suffix}";

        expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
    }
})->with(spineConsoleEntities());

it('authors every literal operator_console key the six consoles reference in the English baseline', function () {
    $referenced = spineConsoleReferencedKeys();

    // Non-vacuity: the scan must actually find keys (a broken regex/path would otherwise pass silently).
    expect($referenced)->not->toBeEmpty();

    $missing = array_values(array_filter(
        $referenced,
        static fn (string $path): bool => ! Lang::has("operator_console.{$path}", 'en', false),
    ));

    // Every literal key (columns/fields/values, the create action, the PR duplicate message) is authored in en.
    expect($missing)->toBe([]);
});

it('renders spine console copy in Italian when the operator locale is it', function (string $key) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing. (Proper-noun labels that are equal in
    // both locales — e.g. product_reference.columns.variant = "Product Variant" — are intentionally not listed.)
    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with([
    'operator_console.format.actions.submit',
    'operator_console.format.columns.lifecycle_state',
    'operator_console.format.affordance.second_actor',
    'operator_console.format.notifications.action_failed',
    'operator_console.case_configuration.actions.reject',
    'operator_console.case_configuration.columns.packaging_type',
    'operator_console.case_configuration.notifications.retired',
    'operator_console.product_variant.columns.vintage',
    'operator_console.product_variant.values.non_vintage',
    'operator_console.product_variant.fields.tasting_notes_help',
    'operator_console.product_variant.notifications.reopened',
    'operator_console.product_reference.actions.reopen',
    'operator_console.product_reference.duplicate_reference',
    'operator_console.product_reference.notifications.submitted',
    'operator_console.sellable_sku.columns.commercial_name',
    'operator_console.sellable_sku.fields.marketing_copy',
    'operator_console.sellable_sku.affordance.second_actor',
    'operator_console.sellable_sku.notifications.activated',
    'operator_console.composite_sku.columns.constituent_count',
    'operator_console.composite_sku.fields.constituents',
    'operator_console.composite_sku.fields.constituents_help',
    'operator_console.composite_sku.notifications.retired',
]);

it('falls back to the English value for an English-invariant label absent in Italian', function (string $entity) {
    App::setLocale('it');

    // `<entity>.label` is an English-invariant domain term (CONTEXT.md): authored only in `en` and intentionally
    // omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent (the non-vacuity
    // guard) yet still resolves — to the English baseline value, per key (never the raw key).
    $key = "operator_console.{$entity}.label";

    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with(spineConsoleEntities());

it('keeps every Italian spine-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    $entities = spineConsoleEntities();
    $belongsToSpine = static function (string $dotKey) use ($entities): bool {
        foreach ($entities as $entity) {
            if (str_starts_with($dotKey, $entity.'.')) {
                return true;
            }
        }

        return false;
    };

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToSpine);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToSpine);

    // English is the final fallback baseline (DEC-127): every authored Italian spine key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the six spine consoles through the operator_console group', function () {
    // Reuse the predecessor's generically-named, OperatorConsole-wide sink scanner (declared in
    // ProductMasterConsoleI18nTest, loaded by the suite). Guard non-vacuity so a future rename fails loudly here
    // rather than silently skipping the scan.
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Catalog')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && ! str_contains($file->getPathname(), 'ProductMaster'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE spine console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every spine console label/column/field/affordance/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
