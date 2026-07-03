<?php

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use Illuminate\Support\Facades\Lang;

// Task 1.2 (parties-enhanced-kyc-threshold; design D6; invariant 12 — no hardcoded user-facing strings). The
// Compliance review-queue enums (ComplianceReviewReason / ThresholdKind, task 2.1) carry NO label() method (the repo
// convention — SanctionsStatus / HoldType have none), so any read surface that renders a review's reason /
// threshold_kind (the operator console's read-only enhanced-KYC panel, task 6.1; a future Compliance dashboard) maps
// the enum BACKING VALUE through the Module-K domain-copy keys `parties.compliance_review.{reason,threshold_kind}.*`.
//
// This is the front-loaded i18n contract for those DOMAIN value labels — the console CHROME (headings, column
// headers, notifications) is guarded separately by CustomerConsoleI18nTest under `operator_console.customer.*`. It
// enumerates every enum CASE and proves each has an authored EN label that resolves to real copy (never the raw
// key), so adding an enum case without its label fails HERE rather than rendering a raw key on a compliance surface.
// Parties copy is EN-only in this repo (there is no lang/it/parties.php); under `it` it falls back per-key to EN
// (DEC-127), so this is an EN-baseline completeness guard.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in tests/Feature);
// no DB is touched (pure locale resolution), so no RefreshDatabase.

it('authors an English review-reason label for every ComplianceReviewReason case', function () {
    // Enumerate the enum so the labelset can never drift from it: a new reason without copy fails here.
    foreach (ComplianceReviewReason::cases() as $reason) {
        $key = "parties.compliance_review.reason.{$reason->value}";

        expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en")
            ->and(trans($key, [], 'en'))->not->toBe($key, "expected {$key} to resolve to real copy, not the raw key");
    }
});

it('authors an English threshold-kind label for every ThresholdKind case', function () {
    foreach (ThresholdKind::cases() as $kind) {
        $key = "parties.compliance_review.threshold_kind.{$kind->value}";

        expect(Lang::has($key, 'en', false))->toBeTrue("expected {$key} to be authored in en")
            ->and(trans($key, [], 'en'))->not->toBe($key, "expected {$key} to resolve to real copy, not the raw key");
    }
});

it('scopes every compliance-review domain label to an enum case (no orphan copy)', function () {
    // The reverse guard: every authored `parties.compliance_review.{reason,threshold_kind}.*` key maps to a real
    // enum case, so a typo'd or stale label (a token no enum backs) is caught rather than silently unused. Each
    // sub-group resolves to its array — narrow the string|array return per group (the CustomerConsoleI18nTest idiom,
    // so array_keys() receives an array, not a mixed element).
    $reasonGroup = trans('parties.compliance_review.reason', [], 'en');
    $kindGroup = trans('parties.compliance_review.threshold_kind', [], 'en');
    assert(is_array($reasonGroup) && is_array($kindGroup));

    $reasonTokens = array_map(static fn (ComplianceReviewReason $r): string => $r->value, ComplianceReviewReason::cases());
    $kindTokens = array_map(static fn (ThresholdKind $k): string => $k->value, ThresholdKind::cases());

    expect(array_keys($reasonGroup))->toEqualCanonicalizing($reasonTokens)
        ->and(array_keys($kindGroup))->toEqualCanonicalizing($kindTokens);
});
