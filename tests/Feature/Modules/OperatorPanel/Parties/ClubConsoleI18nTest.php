<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 5.1 (operator-console-parties-supply-side; design D1–D11; ADR 2026-06-20 + 2026-06-21; invariant 12 — no
// hardcoded user-facing strings). The Club console shipped in tasks 2.1–4.2 — the SECOND non-catalog Parties
// (Module K) console. Like the Producer console it reuses the shared kit at the TRAIT level: `ClubResource extends
// OperatorConsoleResource` for the model labels + the `version` column, and `ViewClub use SurfacesDomainActions`
// for the two status verbs (sunset/close). All its copy routes through the `operator_console.club.*` group.
//
// This is the console's capability-close i18n guard, mirroring `ProducerConsoleI18nTest` and the catalog
// ProductMaster/Spine predecessors. It enumerates every key the console resolves and proves each is authored:
//   (1) EN baseline completeness — every kit-contract key is authored in lang/en, so no label renders raw;
//   (2) Italian rendering — every key the console authors in IT resolves to authored Italian under `it`,
//       distinct from the EN value (not the per-key fallback firing);
//   (3) per-key EN fallback — the English-invariant `label`/`plural_label` (the canonical term "Club",
//       CONTEXT.md) is omitted from `it` yet still resolves to its English baseline value (DEC-127);
//   (4) IT ⊆ EN — every authored Italian club key has an English counterpart (no dangling fallback); and
//   (5) the sink-anchored token scan (reused from ProductMasterConsoleI18nTest) finds no hardcoded user-facing
//       literal in the Club console's Filament classes (invariant 12).
//
// Why enumeration is the only guard for most of these keys: the kit resolves `label`/`plural_label` (off
// OperatorConsoleResource::getModelLabel()/getPluralModelLabel()), `columns.version` (versionColumn()),
// `actions.{sunset,close}` + `notifications.{sunset,closed}` (SurfacesDomainActions::lifecycleAction(),
// concatenated from the verb + the explicit successKey) and `notifications.action_failed`
// (surfaceLifecycleOutcome()) BY STRING CONCATENATION in the base classes — none appears as a literal in the
// per-entity source, so a source scan cannot see them and the lifecycle/create tests (which assert behaviour, not
// label text) would pass even with a dropped key, because __() renders the raw key as the label. The resource's
// OWN keys (`columns.{display_name,producer,registration_flow_type,status}`, every `fields.*`, `actions.create`)
// ARE literal `__()` keys in ClubResource/its pages, but no behaviour test asserts they resolve either — so they
// belong in the same contract. Enumeration is the only guard that catches a dropped key under any locale.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase. The danger-
// notification BODY copy is the domain's (`parties.*`, shipped with the actions), EN-only in this repo and out of
// this operator-surface change's scope; under `it` it falls back per-key to EN (DEC-127).

/**
 * Every key the Club console resolves and so must author — the union of the kit's string-concatenated keys
 * (invisible to a source scan) and the resource's own literal `__()` keys (visible, but unverified by the
 * behaviour tests):
 *   - OperatorConsoleResource: label, plural_label, columns.version (concatenated off i18nKey());
 *   - ClubResource: columns.{display_name,producer,registration_flow_type,status} (the read columns + the own
 *     `status` badge, design D2 — not the kit's `lifecycle_state`) and fields.{display_name,producer,
 *     registration_flow_type,amount,currency,fee,generates_credit,invite_only} (the create-form inputs plus the
 *     view-only Money `fee`);
 *   - ListClubs: actions.create (the header create-LINK label);
 *   - SurfacesDomainActions: actions.{sunset,close} + notifications.{sunset,closed} (the two verbs) +
 *     notifications.action_failed (the rejection title).
 * A console that resolves all of these MUST author them all or a label renders as a raw key under any locale.
 *
 * @return list<string> dot-paths WITHOUT the `operator_console.club.` prefix
 */
function clubConsoleKitKeys(): array
{
    return [
        'label', 'plural_label',
        'columns.display_name', 'columns.producer', 'columns.registration_flow_type', 'columns.status', 'columns.version',
        'fields.display_name', 'fields.producer', 'fields.registration_flow_type', 'fields.amount', 'fields.currency', 'fields.fee', 'fields.generates_credit', 'fields.invite_only',
        'actions.create', 'actions.sunset', 'actions.close',
        'notifications.sunset', 'notifications.closed', 'notifications.action_failed',
    ];
}

/**
 * The kit keys the Club console authors in Italian with a value distinct from English — every kit key except the
 * English-invariant `label`/`plural_label` (the canonical term "Club", CONTEXT.md), which are omitted from `it`
 * and asserted by the EN-fallback test instead. Derived from {@see clubConsoleKitKeys()} so a key added to the
 * contract flows into both datasets automatically.
 *
 * @return list<string>
 */
function clubConsoleItDiffersKeys(): array
{
    return array_values(array_diff(clubConsoleKitKeys(), ['label', 'plural_label']));
}

it('authors every kit-resolved Club-console key in the English baseline', function (string $suffix) {
    // The kit's keys are concatenated in the base classes, never literal in the per-entity files, so a source
    // scan can't see them and the lifecycle/create tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    $key = "operator_console.club.{$suffix}";

    expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
})->with(clubConsoleKitKeys());

it('renders Club-console copy in Italian when the operator locale is it', function (string $suffix) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing.
    $key = "operator_console.club.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with(clubConsoleItDiffersKeys());

it('falls back to the English value for the English-invariant Club label absent in Italian', function (string $suffix) {
    App::setLocale('it');

    // `club.label`/`plural_label` is the canonical structural term (CONTEXT.md): authored only in `en` and
    // intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent (the
    // non-vacuity guard) yet still resolves — to the English baseline value, per key (never the raw key).
    $key = "operator_console.club.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with(['label', 'plural_label']);

it('keeps every Italian Club-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    // Only the `club.*` block. The trailing dot is load-bearing: it excludes a sibling key that merely contains
    // "club", e.g. the producer block's `producer.fields.clubs` ("Operated clubs") — that starts with `producer.`,
    // never `club.` — and any future top-level `clubs.*` group.
    $belongsToClub = static fn (string $dotKey): bool => str_starts_with($dotKey, 'club.');

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToClub);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToClub);

    // Non-vacuity: the IT club block is authored, so the filtered set is not empty.
    expect($itKeys)->not->toBeEmpty();

    // English is the final fallback baseline (DEC-127): every authored Italian club key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the Club console through the operator_console group', function () {
    // Reuse the generically-named, OperatorConsole-wide sink scanner declared in ProductMasterConsoleI18nTest and
    // loaded by the suite. Guard non-vacuity so a future rename fails loudly here rather than silently skipping it
    // (this is why the file must run via --filter or the full suite, never a bare path — design Risks).
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    // Scope to the Club console (`ClubResource` + its Pages) so this guard travels with this change even as the
    // sibling Parties consoles (Producer already shipped, ProducerAgreement next) live under the same
    // `Resources/Parties/` directory. `str_contains(..., 'ClubResource')` matches `ClubResource.php` and every
    // file under `ClubResource/Pages/`, and never `ProducerResource`/`ProducerAgreementResource`.
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Parties')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && str_contains($file->getPathname(), 'ClubResource'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE Club console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every Club console label/column/field/action/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
