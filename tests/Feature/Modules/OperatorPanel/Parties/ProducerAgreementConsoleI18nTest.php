<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 10.1 (operator-console-parties-supply-side; design D1–D8; ADR 2026-06-19 + 2026-06-20 + 2026-06-21;
// invariant 12 — no hardcoded user-facing strings). The ProducerAgreement console shipped in tasks 7.1–9.2 — the
// THIRD non-catalog Parties (Module K) console. Like the Producer and Club consoles it reuses the shared kit at
// the TRAIT level: `ProducerAgreementResource extends OperatorConsoleResource` for the model labels + the
// `version` column, and `ViewProducerAgreement use SurfacesDomainActions` for the two status verbs
// (activate/terminate). All its copy routes through the `operator_console.producer_agreement.*` group.
//
// This is the console's capability-close i18n guard, mirroring `ProducerConsoleI18nTest`/`ClubConsoleI18nTest`
// and the catalog ProductMaster/Spine predecessors. It enumerates every key the console resolves and proves each
// is authored:
//   (1) EN baseline completeness — every kit-contract key is authored in lang/en, so no label renders raw;
//   (2) Italian rendering — every key the console authors in IT resolves to authored Italian under `it`,
//       distinct from the EN value (not the per-key fallback firing);
//   (3) per-key EN fallback — the English-invariant `label`/`plural_label` (the canonical term "Producer
//       agreement", CONTEXT.md § 4.6) is omitted from `it` yet still resolves to its English baseline (DEC-127);
//   (4) IT ⊆ EN — every authored Italian producer_agreement key has an English counterpart (no dangling
//       fallback); and
//   (5) the sink-anchored token scan (reused from ProductMasterConsoleI18nTest) finds no hardcoded user-facing
//       literal in the ProducerAgreement console's Filament classes (invariant 12).
//
// Why enumeration is the only guard for most of these keys: the kit resolves `label`/`plural_label` (off
// OperatorConsoleResource::getModelLabel()/getPluralModelLabel()), `columns.version` (versionColumn()),
// `actions.{activate,terminate}` + `notifications.{activated,terminated}` (SurfacesDomainActions::lifecycleAction(),
// concatenated from the verb + the explicit successKey) and `notifications.action_failed`
// (surfaceLifecycleOutcome()) BY STRING CONCATENATION in the base classes — none appears as a literal in the
// per-entity source, so a source scan cannot see them and the lifecycle/create tests (which assert behaviour, not
// label text) would pass even with a dropped key, because __() renders the raw key as the label. The resource's
// OWN keys (`columns.{producer,club,status,term_start,term_end}`, the `producer_wide` placeholder, every
// `fields.*`, `actions.create`) ARE literal `__()` keys in ProducerAgreementResource/its pages, but no behaviour
// test asserts they resolve either — so they belong in the same contract. Enumeration is the only guard that
// catches a dropped key under any locale.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase. The danger-
// notification BODY copy is the domain's (`parties.*`, shipped with the actions — e.g. the
// IllegalProducerAgreementTransition message), EN-only in this repo and out of this operator-surface change's
// scope; under `it` it falls back per-key to EN (DEC-127).

/**
 * Every key the ProducerAgreement console resolves and so must author — the union of the kit's
 * string-concatenated keys (invisible to a source scan) and the resource's own literal `__()` keys (visible, but
 * unverified by the behaviour tests):
 *   - OperatorConsoleResource: label, plural_label, columns.version (concatenated off i18nKey());
 *   - ProducerAgreementResource: columns.{producer,club,status,term_start,term_end} (the read columns + the own
 *     `status` badge, design D2 — not the kit's `lifecycle_state`), the `producer_wide` placeholder the nullable
 *     `club` column/entry renders (§ 4.6), and fields.{producer,club,term_start,term_end,settlement_cadence} (the
 *     create-form inputs; `settlement_cadence` doubles as the view-only infolist label);
 *   - ListProducerAgreements: actions.create (the header create-LINK label);
 *   - SurfacesDomainActions: actions.{activate,terminate} + notifications.{activated,terminated} (the two verbs) +
 *     notifications.action_failed (the rejection title).
 * A console that resolves all of these MUST author them all or a label renders as a raw key under any locale.
 * There is deliberately NO `supersede` key — supersession is the inline side-effect of activation, never an
 * operator verb (design D8) — and NO `status` field key (an agreement is born `draft`, design D7).
 *
 * @return list<string> dot-paths WITHOUT the `operator_console.producer_agreement.` prefix
 */
function producerAgreementConsoleKitKeys(): array
{
    return [
        'label', 'plural_label',
        'columns.producer', 'columns.club', 'columns.status', 'columns.term_start', 'columns.term_end', 'columns.version',
        'producer_wide',
        'fields.producer', 'fields.club', 'fields.term_start', 'fields.term_end', 'fields.settlement_cadence',
        'actions.create', 'actions.activate', 'actions.terminate',
        'notifications.activated', 'notifications.terminated', 'notifications.action_failed',
    ];
}

/**
 * The kit keys the ProducerAgreement console authors in Italian with a value distinct from English — every kit
 * key except the English-invariant `label`/`plural_label` (the canonical term "Producer agreement", CONTEXT.md
 * § 4.6), which are omitted from `it` and asserted by the EN-fallback test instead. Derived from
 * {@see producerAgreementConsoleKitKeys()} so a key added to the contract flows into both datasets automatically.
 *
 * @return list<string>
 */
function producerAgreementConsoleItDiffersKeys(): array
{
    return array_values(array_diff(producerAgreementConsoleKitKeys(), ['label', 'plural_label']));
}

it('authors every kit-resolved ProducerAgreement-console key in the English baseline', function (string $suffix) {
    // The kit's keys are concatenated in the base classes, never literal in the per-entity files, so a source
    // scan can't see them and the lifecycle/create tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    $key = "operator_console.producer_agreement.{$suffix}";

    expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
})->with(producerAgreementConsoleKitKeys());

it('renders ProducerAgreement-console copy in Italian when the operator locale is it', function (string $suffix) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing.
    $key = "operator_console.producer_agreement.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with(producerAgreementConsoleItDiffersKeys());

it('falls back to the English value for the English-invariant ProducerAgreement label absent in Italian', function (string $suffix) {
    App::setLocale('it');

    // `producer_agreement.label`/`plural_label` is the canonical structural term (CONTEXT.md § 4.6): authored
    // only in `en` and intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is
    // genuinely absent (the non-vacuity guard) yet still resolves — to the English baseline value, per key (never
    // the raw key).
    $key = "operator_console.producer_agreement.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with(['label', 'plural_label']);

it('keeps every Italian ProducerAgreement-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    // Only the `producer_agreement.*` block. The trailing dot is load-bearing: it excludes the sibling `producer.*`
    // block (the Producer console — its keys start with `producer.`, never `producer_agreement.`) and any future
    // top-level `producer_agreements.*` (plural) group.
    $belongsToAgreement = static fn (string $dotKey): bool => str_starts_with($dotKey, 'producer_agreement.');

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToAgreement);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToAgreement);

    // Non-vacuity: the IT producer_agreement block is authored, so the filtered set is not empty.
    expect($itKeys)->not->toBeEmpty();

    // English is the final fallback baseline (DEC-127): every authored Italian producer_agreement key must have an
    // English counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the ProducerAgreement console through the operator_console group', function () {
    // Reuse the generically-named, OperatorConsole-wide sink scanner declared in ProductMasterConsoleI18nTest and
    // loaded by the suite. Guard non-vacuity so a future rename fails loudly here rather than silently skipping it
    // (this is why the file must run via --filter or the full suite, never a bare path — design Risks).
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    // Scope to the ProducerAgreement console (`ProducerAgreementResource` + its Pages) so this guard travels with
    // this change even as the sibling Parties consoles (Producer + Club already shipped) live under the same
    // `Resources/Parties/` directory. `str_contains(..., 'ProducerAgreementResource')` matches
    // `ProducerAgreementResource.php` and every file under `ProducerAgreementResource/Pages/`, and never
    // `ProducerResource`/`ClubResource`.
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Parties')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && str_contains($file->getPathname(), 'ProducerAgreementResource'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE ProducerAgreement console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every ProducerAgreement console label/column/field/action/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
