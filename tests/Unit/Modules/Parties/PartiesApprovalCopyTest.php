<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

// Pins the Producer separation-of-duties copy (change parties-producer-approval-sod, task 1.1; design D1/D4;
// party-registry — Requirement: Producer Lifecycle; Module K PRD § 4.4 / AC-K-J-10; invariant 12 — no
// hardcoded user-facing strings). The SoD guard's violation exception (task 1.2) resolves these two keys, so
// each must exist in EN (the authored baseline AND final fallback, DEC-127) AND render authored Italian under
// `it`. Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator available so
// __()/trans() resolve lang/{en,it}/parties.php instead of echoing the key back.

uses(TestCase::class);

// 'Producer' is absent from every literal template, so its presence in the resolved message proves :entity was
// interpolated (not merely that the copy spells a similar word) AND that the key is defined (a missing key makes
// Laravel echo the key back unchanged).
it('resolves the SoD approval copy non-empty in en, naming the :entity', function (string $key) {
    // `->not->toBe($key)` proves the key is defined (a missing key makes Laravel echo it back), and
    // `->toContain('Producer')` proves the resolved copy is a non-empty string with :entity interpolated
    // ('Producer' appears in no literal template — its presence is the interpolation proof, not a similar word).
    expect(trans($key, ['entity' => 'Producer'], 'en'))
        ->not->toBe($key)
        ->and(trans($key, ['entity' => 'Producer'], 'en'))->toContain('Producer');
})->with([
    'parties.approval.requires_operator_principal',
    'parties.approval.creator_may_not_approve',
]);

it('renders the SoD approval copy in authored Italian under it', function (string $key) {
    App::setLocale('it');

    // Genuinely authored in `it` (Lang::has third arg false = no fallback) AND distinct from the English value
    // — proves Italian rendering, not the EN fallback firing (the ProductMasterConsoleI18nTest pattern).
    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(trans($key, ['entity' => 'Producer'], 'it'))->not->toBe('')
        ->and(trans($key, ['entity' => 'Producer'], 'it'))->not->toBe($key)
        ->and(trans($key, ['entity' => 'Producer'], 'it'))->not->toBe(trans($key, ['entity' => 'Producer'], 'en'));
})->with([
    'parties.approval.requires_operator_principal',
    'parties.approval.creator_may_not_approve',
]);

it('keeps every Italian parties key backed by an English baseline key (DEC-127)', function () {
    $en = trans('parties', [], 'en');
    $it = trans('parties', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    $enKeys = array_keys(Arr::dot($en));
    $itKeys = array_keys(Arr::dot($it));

    // English is the final fallback baseline (DEC-127): every authored Italian key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});
