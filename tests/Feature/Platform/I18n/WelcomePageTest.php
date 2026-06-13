<?php

use Illuminate\Support\Facades\App;

use function Pest\Laravel\get;

// Pins task 2.4 — the welcome.blade.php remediation (foundations-money-i18n-flags; design
// D4; debt S3; i18n capability — Requirement: No Hardcoded User-Facing Strings, scenario
// "The welcome view renders only keyed strings"). The legacy 72KB default Laravel page is
// replaced by a minimal holding page whose every visible string resolves through a
// __('welcome.*') key authored in lang/ (task 2.2) — CLAUDE.md invariant 12.
//
// Feature test: it boots the HTTP kernel + translator (Pest binds the Laravel TestCase only
// in tests/Feature). No DB → no RefreshDatabase. The HTTP call uses pest-plugin-laravel's
// typed global get() (@return TestResponse) rather than $this->get() — the closure's $this
// is a Pest\PendingCalls\TestCall under static analysis, so $this->get() is unresolvable at
// PHPStan max; the global function is the idiomatic, type-clean entry point.

it('renders the welcome holding page with a 200', function () {
    get('/')->assertOk();
});

it('renders user-facing copy through translation keys, not literals', function () {
    // Under the default locale (en) the page shows the RESOLVED English values, never the
    // bare keys. Asserting the resolved value is present AND the key string is absent makes
    // this non-vacuous: an unrendered/hardcoded page would red on one side or the other.
    $tagline = trans('welcome.tagline', [], 'en');
    $comingSoon = trans('welcome.coming_soon', [], 'en');

    // Guard: the keys actually resolve to copy (a missing lang entry returns the key verbatim).
    expect($tagline)->not->toBe('welcome.tagline')
        ->and($comingSoon)->not->toBe('welcome.coming_soon');

    get('/')
        ->assertOk()
        ->assertSee($tagline)
        ->assertSee($comingSoon)
        ->assertDontSee('welcome.tagline')
        ->assertDontSee('welcome.coming_soon');
});

it('localizes the rendered copy when the active locale is switched', function () {
    App::setLocale('it');

    // `welcome.tagline` is authored in `it` (lang/it/welcome.php), so the page renders the
    // Italian text — genuinely different from the English baseline, proving the locale switch
    // (not the per-key English fallback) is what changed the copy.
    $italian = trans('welcome.tagline', [], 'it');

    expect($italian)->not->toBe(trans('welcome.tagline', [], 'en'));

    get('/')
        ->assertOk()
        ->assertSee($italian);
});
