<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 5.1 (operator-console-parties-producer; design D8; ADR 2026-06-20; invariant 12 — no hardcoded
// user-facing strings). The Producer console shipped in tasks 1.1–4.2, routing its copy through the
// `operator_console.producer` translation group and reusing the shared kit at the trait level (the
// non-catalog pattern): `ProducerResource extends OperatorConsoleResource` for the model labels + the
// `version` column, and `ViewProducer use SurfacesDomainActions` for the six lifecycle actions.
//
// The kit resolves a set of keys for the entity off its i18nKey() BY STRING CONCATENATION in the base
// classes — `OperatorConsoleResource::getModelLabel()/getPluralModelLabel()` ⇒ `…producer.{label,plural_label}`
// and `versionColumn()` ⇒ `…producer.columns.version`; `SurfacesDomainActions::lifecycleAction()` ⇒ the six
// `actions.<Str::snake(verb)>` labels + the six `notifications.<successKey>` success titles, and
// `surfaceLifecycleOutcome()` ⇒ `notifications.action_failed`. None of those keys appears as a literal in the
// per-entity source, so a source scan cannot prove they are authored, and the lifecycle/create tests assert
// behaviour (not label text) — they would pass even with a dropped key, because __() renders the raw key as the
// label. Enumerating the kit contract and asserting `Lang::has(…, 'en', false)` is the only guard that catches
// it (the two `status`/`kyc_status` columns are listed too: they are this resource's own analogue of the kit's
// `lifecycle_state` column — `columns.status`/`columns.kyc_status` — kept in the same contract).
//
// This is the console's capability-close i18n guard, mirroring the predecessors' ProductMaster/Spine tests:
//   (1) EN baseline completeness — every kit-resolved key is authored in lang/en, so no label renders raw;
//   (2) Italian rendering — every key the console authors in IT resolves to authored Italian under `it`,
//       distinct from the EN value (not the per-key fallback firing);
//   (3) per-key EN fallback — the English-invariant `label`/`plural_label` (the canonical term "Producer",
//       CONTEXT.md) is omitted from `it` yet still resolves to its English baseline value (DEC-127);
//   (4) IT ⊆ EN — every authored Italian producer key has an English counterpart (no dangling fallback); and
//   (5) the sink-anchored token scan (reused from ProductMasterConsoleI18nTest) finds no hardcoded user-facing
//       literal in the Producer console's Filament classes (invariant 12).
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase. The
// danger-notification BODY copy is the domain's (`parties.*`, shipped with the actions), EN-only in this repo
// and out of this operator-surface change's scope; under `it` it falls back per-key to EN (DEC-127).

/**
 * The keys the shared console kit resolves for the Producer entity off its i18nKey(), by string concatenation in
 * the base classes (so they are invisible to a per-entity source scan), plus this resource's own two status
 * columns (the kit's `lifecycle_state` analogue):
 *   - OperatorConsoleResource: label, plural_label, columns.version;
 *   - ProducerResource: columns.status, columns.kyc_status (own badge columns — Producer's two FSMs, design D2);
 *   - SurfacesDomainActions: the six action labels + their six success notifications + notifications.action_failed.
 * A console that resolves all of these MUST author them all or a label renders as a raw key under any locale.
 *
 * @return list<string> dot-paths WITHOUT the `operator_console.producer.` prefix
 */
function producerConsoleKitKeys(): array
{
    return [
        'label', 'plural_label',
        'columns.status', 'columns.kyc_status', 'columns.version',
        'actions.activate', 'actions.retire',
        'actions.require_kyc', 'actions.waive_kyc', 'actions.verify_kyc', 'actions.reject_kyc',
        'notifications.activated', 'notifications.retired',
        'notifications.kyc_required', 'notifications.kyc_waived', 'notifications.kyc_verified', 'notifications.kyc_rejected',
        'notifications.action_failed',
    ];
}

/**
 * The kit keys the Producer console authors in Italian with a value distinct from English — every kit key except
 * the English-invariant `label`/`plural_label` (the canonical term "Producer", CONTEXT.md), which are omitted
 * from `it` and asserted by the EN-fallback test instead. Derived from {@see producerConsoleKitKeys()} so a key
 * added to the contract flows into both datasets automatically.
 *
 * @return list<string>
 */
function producerConsoleItDiffersKeys(): array
{
    return array_values(array_diff(producerConsoleKitKeys(), ['label', 'plural_label']));
}

it('authors every kit-resolved Producer-console key in the English baseline', function (string $suffix) {
    // The kit's keys are concatenated in the base classes, never literal in the per-entity files, so a source
    // scan can't see them and the lifecycle/create tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    $key = "operator_console.producer.{$suffix}";

    expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
})->with(producerConsoleKitKeys());

it('renders Producer-console copy in Italian when the operator locale is it', function (string $suffix) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing.
    $key = "operator_console.producer.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with(producerConsoleItDiffersKeys());

it('falls back to the English value for the English-invariant Producer label absent in Italian', function (string $suffix) {
    App::setLocale('it');

    // `producer.label`/`plural_label` is the canonical structural term (CONTEXT.md): authored only in `en` and
    // intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent (the
    // non-vacuity guard) yet still resolves — to the English baseline value, per key (never the raw key).
    $key = "operator_console.producer.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with(['label', 'plural_label']);

it('keeps every Italian Producer-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    // Only the `producer.*` block — not the sibling `product_master.*` (whose own keys include a `columns.producer`
    // that must not be swept in here). `product_master.…` keys start with `product_master.`, never `producer.`.
    $belongsToProducer = static fn (string $dotKey): bool => str_starts_with($dotKey, 'producer.');

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToProducer);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToProducer);

    // Non-vacuity: the IT producer block is authored, so the filtered set is not empty.
    expect($itKeys)->not->toBeEmpty();

    // English is the final fallback baseline (DEC-127): every authored Italian producer key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the Producer console through the operator_console group', function () {
    // Reuse the generically-named, OperatorConsole-wide sink scanner declared in ProductMasterConsoleI18nTest and
    // loaded by the suite. Guard non-vacuity so a future rename fails loudly here rather than silently skipping it.
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    // Scope to the Producer console (`ProducerResource` + its Pages) so this guard travels with this change even
    // as later Parties consoles (Club, ProducerAgreement, …) land under the same `Resources/Parties/` directory.
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Parties')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && str_contains($file->getPathname(), 'ProducerResource'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE Producer console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every Producer console label/column/field/action/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
