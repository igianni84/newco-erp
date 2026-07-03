<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 7.1 (operator-console-parties-membership; invariant 12 — no hardcoded user-facing strings). The demand-side
// membership console shipped across this change: the read-only ProfileResource + its three Pages (groups 1–2) and
// the eight Profile lifecycle verbs appended to ViewProfile (groups 3–5), all routing copy through the
// `operator_console.profile.*` group; PLUS the three Account status verbs appended to ViewCustomer in group 6, whose
// copy lives in the `operator_console.customer.*` group (the verbs are on ViewCustomer) under keys NOT present in
// `customerConsoleKitKeys()` (that contract predates this change). So this guard spans TWO blocks.
//
// This is the console's capability-close i18n guard, mirroring the sibling Customer/Club consoles and the catalog
// ProductMaster/Spine predecessors. It enumerates every key the console resolves and proves each is authored:
//   (1) EN baseline completeness — every kit-contract key is authored in lang/en, so no label renders raw;
//   (2) Italian rendering — every key the console authors in IT resolves to authored Italian under `it`,
//       distinct from the EN value (not the per-key fallback firing);
//   (3) per-key EN fallback — the English-invariant `profile.label`/`plural_label` (the canonical term "Profile",
//       CONTEXT.md) is omitted from `it` yet still resolves to its English baseline value (DEC-127);
//   (4) loanword identity — `profile.columns.club`/`profile.fields.club` ARE authored in IT but with the value
//       "Club" (no Italian translation), identical to EN, so they cannot sit in the distinctness set above;
//   (5) IT ⊆ EN — every authored Italian profile key has an English counterpart (no dangling fallback); and
//   (6) the sink-anchored token scan (reused from ProductMasterConsoleI18nTest) finds no hardcoded user-facing
//       literal in the Profile console's Filament classes (invariant 12).
//
// Why enumeration is the only guard for many of these keys: the kit resolves `label`/`plural_label` (off
// OperatorConsoleResource::getModelLabel()/getPluralModelLabel()), `columns.version` (versionColumn()), and every
// `actions.*` + `notifications.*` verb pair (SurfacesDomainActions::lifecycleAction(), concatenated from the verb +
// the explicit successKey) and `notifications.action_failed` (surfaceLifecycleOutcome()) BY STRING CONCATENATION in
// the base classes — none appears as a literal in the per-entity source, so a source scan cannot see them and the
// lifecycle/create tests (which assert behaviour, not label text) would pass even with a dropped key, because __()
// renders the raw key as the label. The resource's OWN keys (`columns.{customer,club,state}`, every `fields.*`,
// `tabs.*`, `actions.create`) ARE literal `__()` keys in ProfileResource/its pages, but no behaviour test asserts
// they resolve either — so they belong in the same contract. Enumeration is the only guard that catches a dropped
// key under any locale.
//
// The customer.* Account keys' sink hygiene (ViewCustomer) and IT ⊆ EN are already covered by CustomerConsoleI18nTest,
// which scans every CustomerResource file (incl. ViewCustomer) and asserts IT ⊆ EN over the whole `customer.` block;
// this file adds the EN-completeness + IT-distinct guards the older contract does not extend to the new verbs.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in tests/Feature); no
// DB is touched (pure locale + static source scan), so no RefreshDatabase. The danger-notification BODY copy is the
// domain's (`parties.*`, shipped with the actions), EN-only in this repo and out of this operator-surface change's
// scope; under `it` it falls back per-key to EN (DEC-127).

/**
 * Every operator_console key the Profile membership console resolves and so must author. Two blocks, full dot-paths:
 *   - profile.*  — OperatorConsoleResource (label, plural_label, columns.version, concatenated off i18nKey());
 *     ProfileResource (columns.{customer,club,state} + the own `state` badge, design D2 — not the kit's
 *     `lifecycle_state`; fields.{tier,lapsed_at,cancellation_reason,customer,club}); ListProfiles
 *     (tabs.{pending,all} approval queue, actions.create header link); and SurfacesDomainActions on ViewProfile —
 *     the eight verbs actions.{approve,decline,suspend,reactivate,lapse,renew,cancel,deactivate} +
 *     notifications.{approved,declined,suspended,reactivated,lapsed,renewed,cancelled,deactivated} +
 *     notifications.action_failed (the rejection title). The `activate` verb was removed with RM-03 (MVP-DEC-016) —
 *     approval reaches `active` atomically, so its `actions.activate` / `notifications.activated` keys are gone too.
 *   - customer.*  — the three Account status verbs added to ViewCustomer in group 6:
 *     actions.{suspend_account,reactivate_account,close_account} +
 *     notifications.{account_suspended,account_reactivated,account_closed}. They live in the customer block but are
 *     absent from customerConsoleKitKeys() (that contract predates this change), so this is their only
 *     EN-completeness + IT-distinct guard.
 * A console that resolves all of these MUST author them all or a label renders as a raw key under any locale.
 *
 * @return list<string> full operator_console.* dot-paths
 */
function profileConsoleKitKeys(): array
{
    return [
        // --- profile.* block ---
        'operator_console.profile.label',
        'operator_console.profile.plural_label',
        'operator_console.profile.columns.customer',
        'operator_console.profile.columns.club',
        'operator_console.profile.columns.state',
        'operator_console.profile.columns.version',
        'operator_console.profile.fields.tier',
        'operator_console.profile.fields.lapsed_at',
        'operator_console.profile.fields.cancellation_reason',
        'operator_console.profile.fields.customer',
        'operator_console.profile.fields.club',
        'operator_console.profile.tabs.pending',
        'operator_console.profile.tabs.all',
        'operator_console.profile.actions.create',
        'operator_console.profile.actions.approve',
        'operator_console.profile.actions.decline',
        'operator_console.profile.actions.suspend',
        'operator_console.profile.actions.reactivate',
        'operator_console.profile.actions.lapse',
        'operator_console.profile.actions.renew',
        'operator_console.profile.actions.cancel',
        'operator_console.profile.actions.deactivate',
        'operator_console.profile.notifications.approved',
        'operator_console.profile.notifications.declined',
        'operator_console.profile.notifications.suspended',
        'operator_console.profile.notifications.reactivated',
        'operator_console.profile.notifications.lapsed',
        'operator_console.profile.notifications.renewed',
        'operator_console.profile.notifications.cancelled',
        'operator_console.profile.notifications.deactivated',
        'operator_console.profile.notifications.action_failed',
        // --- customer.* Account verbs (group 6, on ViewCustomer; absent from customerConsoleKitKeys()) ---
        'operator_console.customer.actions.suspend_account',
        'operator_console.customer.actions.reactivate_account',
        'operator_console.customer.actions.close_account',
        'operator_console.customer.notifications.account_suspended',
        'operator_console.customer.notifications.account_reactivated',
        'operator_console.customer.notifications.account_closed',
    ];
}

/**
 * The kit keys the Profile console authors in Italian with a value DISTINCT from English — every kit key except:
 *   - `profile.label`/`plural_label`, the English-invariant canonical term "Profile" (CONTEXT.md), omitted from `it`
 *     and asserted by the EN-fallback test instead (DEC-127); and
 *   - `profile.columns.club`/`profile.fields.club`, the loanword "Club" (no Italian translation), authored in `it`
 *     but with a value IDENTICAL to EN — asserted by the loanword-identity test instead.
 * Derived from {@see profileConsoleKitKeys()} so a key added to the contract flows into the dataset automatically.
 *
 * @return list<string>
 */
function profileConsoleItDiffersKeys(): array
{
    return array_values(array_diff(profileConsoleKitKeys(), [
        'operator_console.profile.label',
        'operator_console.profile.plural_label',
        'operator_console.profile.columns.club',
        'operator_console.profile.fields.club',
    ]));
}

it('authors every kit-resolved Profile-console key in the English baseline', function (string $key) {
    // The kit's verb keys are concatenated in the base classes, never literal in the per-entity files, so a source
    // scan can't see them and the lifecycle/create tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
})->with(profileConsoleKitKeys());

it('renders Profile-console copy in Italian when the operator locale is it', function (string $key) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing.
    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with(profileConsoleItDiffersKeys());

it('falls back to the English value for the English-invariant Profile label absent in Italian', function (string $key) {
    App::setLocale('it');

    // `profile.label`/`plural_label` is the canonical structural term (CONTEXT.md): authored only in `en` and
    // intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent (the
    // non-vacuity guard) yet still resolves — to the English baseline value, per key (never the raw key).
    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with([
    'operator_console.profile.label',
    'operator_console.profile.plural_label',
]);

it('authors the Club loanword identically in both locales', function (string $key) {
    App::setLocale('it');

    // "Club" has no Italian translation, so `columns.club`/`fields.club` are authored in `it` with a value IDENTICAL
    // to EN. They are genuinely authored in `it` (the non-vacuity guard — NOT the EN per-key fallback firing) yet
    // equal the EN value, so they cannot sit in the distinctness dataset; this is their explicit guard, and it
    // documents exactly why they are carved out of {@see profileConsoleItDiffersKeys()}.
    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->toBe('Club');
})->with([
    'operator_console.profile.columns.club',
    'operator_console.profile.fields.club',
]);

it('keeps every Italian Profile-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    // Only the `profile.*` block (the block this change introduces). The customer.* Account keys' IT ⊆ EN is already
    // guarded by CustomerConsoleI18nTest over the whole `customer.` block. The trailing dot is load-bearing: it
    // scopes to this entity and excludes any sibling key that merely contains "profile".
    $belongsToProfile = static fn (string $dotKey): bool => str_starts_with($dotKey, 'profile.');

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToProfile);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToProfile);

    // Non-vacuity: the IT profile block is authored, so the filtered set is not empty.
    expect($itKeys)->not->toBeEmpty();

    // English is the final fallback baseline (DEC-127): every authored Italian profile key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the Profile console through the operator_console group', function () {
    // Reuse the generically-named, OperatorConsole-wide sink scanner declared in ProductMasterConsoleI18nTest and
    // loaded by the suite. Guard non-vacuity so a future rename fails loudly here rather than silently skipping it
    // (this is why the file must run via --filter or the full suite, never a bare path — lesson 2026-06-20).
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    // Scope to the Profile console (`ProfileResource` + its Pages) so this guard travels with this change even as the
    // sibling Parties consoles (Customer, Club, Producer, ProducerAgreement) live under the same `Resources/Parties/`
    // directory. `str_contains(..., 'ProfileResource')` matches `ProfileResource.php` and every file under
    // `ProfileResource/Pages/`, and never the sibling resources. (The Account verbs added to ViewCustomer in group 6
    // are sink-scanned by CustomerConsoleI18nTest, which already covers every CustomerResource file incl. ViewCustomer.)
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Parties')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && str_contains($file->getPathname(), 'ProfileResource'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE Profile console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every Profile console label/column/field/tab/action/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
