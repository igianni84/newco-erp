<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 4.1 (operator-console-parties-customer; design D1–D9; ADR 2026-06-21; invariant 12 — no hardcoded
// user-facing strings). The Customer console shipped in tasks 1.1–3.2 — the FIRST demand-side Parties
// (Module K) console. Like the Producer/Club consoles it reuses the shared kit at the TRAIT level (the
// non-catalog pattern): `CustomerResource extends OperatorConsoleResource` for the model labels + the
// `version` column, and `ViewCustomer use SurfacesDomainActions` for the four status verbs (activate /
// suspend / reactivate / close, design D1/D4). All its copy routes through the `operator_console.customer.*`
// group.
//
// This is the console's capability-close i18n guard, mirroring `ProducerConsoleI18nTest` / `ClubConsoleI18nTest`
// and the catalog ProductMaster/Spine predecessors. It enumerates every key the console resolves and proves each
// is authored:
//   (1) EN baseline completeness — every kit-contract key is authored in lang/en, so no label renders raw;
//   (2) Italian rendering — every key the console authors in IT resolves to authored Italian under `it`,
//       distinct from the EN value (not the per-key fallback firing);
//   (3) per-key EN fallback — the English-invariant `label`/`plural_label` (the canonical term "Customer",
//       CONTEXT.md) is omitted from `it` yet still resolves to its English baseline value (DEC-127);
//   (4) IT ⊆ EN — every authored Italian customer key has an English counterpart (no dangling fallback); and
//   (5) the sink-anchored token scan (reused from ProductMasterConsoleI18nTest) finds no hardcoded user-facing
//       literal in the Customer console's Filament classes (invariant 12).
//
// Why enumeration is the only guard for half of these keys: the kit resolves `label`/`plural_label` (off
// OperatorConsoleResource::getModelLabel()/getPluralModelLabel()), `columns.version` (versionColumn()),
// `actions.{activate,suspend,reactivate,close}` + `notifications.{activated,suspended,reactivated,closed}`
// (SurfacesDomainActions::lifecycleAction(), concatenated from the verb + the explicit successKey) and
// `notifications.action_failed` (surfaceLifecycleOutcome()) BY STRING CONCATENATION in the base classes — none
// appears as a literal in the per-entity source, so a source scan cannot see them and the lifecycle/create tests
// (which assert behaviour, not label text) would pass even with a dropped key, because __() renders the raw key
// as the label. The resource's OWN keys (`columns.{name,email,status,kyc_status,sanctions_status,account_status,
// profiles}`, every `fields.*`, `actions.create`) ARE literal `__()` keys in CustomerResource/its pages, but no
// behaviour test asserts they resolve either — so they belong in the same contract. Enumeration is the only guard
// that catches a dropped key under any locale.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase. The danger-
// notification BODY copy is the domain's (`parties.*`, shipped with the actions), EN-only in this repo and out of
// this operator-surface change's scope; under `it` it falls back per-key to EN (DEC-127).

/**
 * Every key the Customer console resolves and so must author — the union of the kit's string-concatenated keys
 * (invisible to a source scan) and the resource's own literal `__()` keys (visible, but unverified by the
 * behaviour tests):
 *   - OperatorConsoleResource: label, plural_label, columns.version (concatenated off i18nKey());
 *   - CustomerResource: columns.{name,email,status,kyc_status,sanctions_status,account_status,profiles} (the read
 *     columns + the three orthogonal status badges, design D2 — not the kit's `lifecycle_state`) and
 *     fields.{email,name,phone,date_of_birth,preferred_currency,preferred_locale} (the create-form inputs, also
 *     the view-infolist personal-data labels);
 *   - ListCustomers: actions.create (the header create-LINK label);
 *   - SurfacesDomainActions: actions.{activate,suspend,reactivate,close} +
 *     notifications.{activated,suspended,reactivated,closed} (the four verbs) + notifications.action_failed
 *     (the rejection title);
 *   - the Holds surface (operator-console-parties-holds): the place-Hold header action + the per-row lift action
 *     (actions.{place_hold,lift_hold}) with their form inputs (fields.{hold_type,hold_scope,profile,reason,
 *     lift_reason}) and outcome titles (notifications.{hold_placed,hold_lifted}; action_failed is reused), plus
 *     the read-only Holds table column headers (holds.columns.{hold_type,scope_type,status,reason,placed_by,
 *     placed_at,lifted_by,lifted_at}). Front-loaded here (this change, task 1.3) ahead of the resolving code in
 *     tasks 2–4, so the i18n contract is green before the surface lands.
 *   - the KYC + sanctions surface (operator-console-parties-kyc-sanctions): the three form-less KYC verbs + the
 *     one form-bearing screening verb (actions.{require_kyc,record_kyc_verified,record_kyc_rejected,
 *     record_screening}) with the screening form inputs (fields.{screening_verdict,screening_source}) and the four
 *     success titles (notifications.{kyc_required,kyc_verified,kyc_rejected,screening_recorded}; action_failed is
 *     reused). Front-loaded here (this change, task 1.3) ahead of the resolving code in tasks 2–3, so the i18n
 *     contract is green before the surface lands.
 *   - the GDPR data-rights surface (parties-anonymisation, task 6.1): the two form-less write-through verbs
 *     (actions.{anonymise,export}) — `anonymise` visibility-gated to a not-yet-anonymised Customer, `export`
 *     ungated — with their two success titles (notifications.{anonymised,exported}; action_failed reused for the
 *     anonymise compliance-Hold block). Authored WITH the resolving code in this change (not front-loaded).
 *   - the enhanced-KYC & Compliance-review read surface (parties-enhanced-kyc-threshold, task 6.1): the read-only
 *     panel's flag/timestamp entries + the open review-queue table headers
 *     (compliance_reviews.{enhanced_kyc_flag,enhanced_kyc_at} + compliance_reviews.columns.{reason,threshold_kind,
 *     amount,opened_at}). Front-loaded here (this change, task 1.2) ahead of the resolving code in task 6.1, so the
 *     i18n contract is green before the surface lands. Two sibling contracts are guarded ELSEWHERE, not here: the
 *     section heading `customer.sections.compliance_reviews` (the sections group is verified by the view render, as
 *     the pre-existing `sections.*` keys are), and the DOMAIN enum-value labels `parties.compliance_review.*` that
 *     render the review reason / threshold_kind VALUES (guarded by ComplianceReviewCopyTest — Module-K domain copy,
 *     not console chrome).
 * A console that resolves all of these MUST author them all or a label renders as a raw key under any locale.
 *
 * @return list<string> dot-paths WITHOUT the `operator_console.customer.` prefix
 */
function customerConsoleKitKeys(): array
{
    return [
        'label', 'plural_label',
        'columns.name', 'columns.email', 'columns.status', 'columns.kyc_status', 'columns.sanctions_status', 'columns.account_status', 'columns.profiles', 'columns.version',
        'fields.email', 'fields.name', 'fields.phone', 'fields.date_of_birth', 'fields.preferred_currency', 'fields.preferred_locale',
        'fields.hold_type', 'fields.hold_scope', 'fields.profile', 'fields.reason', 'fields.lift_reason',
        'fields.screening_verdict', 'fields.screening_source',
        'actions.create', 'actions.activate', 'actions.suspend', 'actions.reactivate', 'actions.close',
        'actions.place_hold', 'actions.lift_hold',
        'actions.require_kyc', 'actions.record_kyc_verified', 'actions.record_kyc_rejected', 'actions.record_screening',
        'actions.anonymise', 'actions.export',
        'holds.columns.hold_type', 'holds.columns.scope_type', 'holds.columns.status', 'holds.columns.reason', 'holds.columns.placed_by', 'holds.columns.placed_at', 'holds.columns.lifted_by', 'holds.columns.lifted_at',
        'compliance_reviews.enhanced_kyc_flag', 'compliance_reviews.enhanced_kyc_at',
        'compliance_reviews.columns.reason', 'compliance_reviews.columns.threshold_kind', 'compliance_reviews.columns.amount', 'compliance_reviews.columns.opened_at',
        'notifications.activated', 'notifications.suspended', 'notifications.reactivated', 'notifications.closed', 'notifications.action_failed',
        'notifications.hold_placed', 'notifications.hold_lifted',
        'notifications.kyc_required', 'notifications.kyc_verified', 'notifications.kyc_rejected', 'notifications.screening_recorded',
        'notifications.anonymised', 'notifications.exported',
    ];
}

/**
 * The kit keys the Customer console authors in Italian with a value distinct from English — every kit key except
 * the English-invariant `label`/`plural_label` (the canonical term "Customer", CONTEXT.md), which are omitted
 * from `it` and asserted by the EN-fallback test instead. Derived from {@see customerConsoleKitKeys()} so a key
 * added to the contract flows into both datasets automatically.
 *
 * @return list<string>
 */
function customerConsoleItDiffersKeys(): array
{
    return array_values(array_diff(customerConsoleKitKeys(), ['label', 'plural_label']));
}

it('authors every kit-resolved Customer-console key in the English baseline', function (string $suffix) {
    // The kit's keys are concatenated in the base classes, never literal in the per-entity files, so a source
    // scan can't see them and the lifecycle/create tests (which assert behaviour, not label text) would pass even
    // with a missing key — __() would just render the raw key as the label. This is the only guard that catches it.
    $key = "operator_console.customer.{$suffix}";

    expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en");
})->with(customerConsoleKitKeys());

it('renders Customer-console copy in Italian when the operator locale is it', function (string $suffix) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the English
    // value, so this proves Italian rendering, not the EN fallback firing.
    $key = "operator_console.customer.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with(customerConsoleItDiffersKeys());

it('falls back to the English value for the English-invariant Customer label absent in Italian', function (string $suffix) {
    App::setLocale('it');

    // `customer.label`/`plural_label` is the canonical structural term (CONTEXT.md): authored only in `en` and
    // intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent (the
    // non-vacuity guard) yet still resolves — to the English baseline value, per key (never the raw key).
    $key = "operator_console.customer.{$suffix}";

    expect(Lang::has($key, 'it', false))->toBeFalse("expected {$key} to be omitted from it")
        ->and(__($key))->toBe(trans($key, [], 'en'))
        ->and(__($key))->not->toBe($key);
})->with(['label', 'plural_label']);

it('keeps every Italian Customer-console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    // Only the `customer.*` block. The trailing dot is load-bearing: it scopes to this entity and excludes any
    // sibling key that merely contains "customer" (e.g. another block's `columns.customer`) — those start with
    // their own block prefix, never `customer.`.
    $belongsToCustomer = static fn (string $dotKey): bool => str_starts_with($dotKey, 'customer.');

    $enKeys = array_filter(array_keys(Arr::dot($en)), $belongsToCustomer);
    $itKeys = array_filter(array_keys(Arr::dot($it)), $belongsToCustomer);

    // Non-vacuity: the IT customer block is authored, so the filtered set is not empty.
    expect($itKeys)->not->toBeEmpty();

    // English is the final fallback baseline (DEC-127): every authored Italian customer key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});

it('routes every user-facing string in the Customer console through the operator_console group', function () {
    // Reuse the generically-named, OperatorConsole-wide sink scanner declared in ProductMasterConsoleI18nTest and
    // loaded by the suite. Guard non-vacuity so a future rename fails loudly here rather than silently skipping it
    // (this is why the file must run via --filter or the full suite, never a bare path — design Risks).
    expect(function_exists('scanOperatorConsoleHardcodedSinks'))
        ->toBeTrue('expected the shared operator-console sink scanner to be loaded');

    // Scope to the Customer console (`CustomerResource` + its Pages) so this guard travels with this change even
    // as the sibling Parties consoles (Producer, Club, ProducerAgreement) live under the same `Resources/Parties/`
    // directory. `str_contains(..., 'CustomerResource')` matches `CustomerResource.php` and every file under
    // `CustomerResource/Pages/`, and never the sibling resources.
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament/Resources/Parties')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
            && str_contains($file->getPathname(), 'CustomerResource'),
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE Customer console classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every Customer console label/column/field/action/notification routes through __(); none is hardcoded.
    expect($violations)->toBe([]);
});
