<?php

// Pins the docs/glossary that task 5.1 (openspec change foundations-money-i18n-flags, design D7)
// brought to present tense now that the F1 3/3 platform primitives exist: the CONTEXT.md glossary
// terms (Money, Currency, FX Rate, Dual-Currency Amount, Actor context, Supported locale,
// Translatable text, Feature flag / EXT-1), the NS-path-as-universal-fallback convention
// (docs/feature-flags.md, task 3.2), the lang/ file convention (docs/i18n.md, task 2.2) and the
// GUIDE.md F1 status line. Reads run through developerDoc() (defined in EventSubstrateDocsTest) —
// the shared reader that throws on a missing file, so a renamed/deleted doc reds the suite instead
// of a token pin passing vacuously; this file re-proves that guard for its own paths.

function glossaryDoc(): string
{
    return developerDoc('CONTEXT.md');
}

function featureFlagsDoc(): string
{
    return developerDoc('docs/feature-flags.md');
}

function i18nDoc(): string
{
    return developerDoc('docs/i18n.md');
}

function buildGuide(): string
{
    return developerDoc('GUIDE.md');
}

it('CONTEXT.md defines the eight F1 platform glossary terms', function () {
    expect(glossaryDoc())
        ->toContain('**Money**')
        ->toContain('**Currency**')
        ->toContain('**FX Rate**')
        ->toContain('**Dual-Currency Amount**')
        ->toContain('**Actor context**')
        ->toContain('**Supported locale**')
        ->toContain('**Translatable text**')
        ->toContain('**Feature flag / EXT-1**');
});

it('CONTEXT.md money terms carry the discipline (minor units, ISO 4217, fail-closed, decimal-string FX)', function () {
    expect(glossaryDoc())
        ->toContain('minor units')      // Money — integer minor units, never a float
        ->toContain('ISO 4217')         // Currency — ISO 4217 code + exponent
        ->toContain('fail-closed')      // Currency — unknown code rejected, not assumed exp 2
        ->toContain('decimal string');  // FX Rate — exact decimal string, never a float
});

it('CONTEXT.md money terms carry their _Avoid_ disambiguations (house format)', function () {
    // The house format pairs a confusable term with an _Avoid_ line; the money primitives add one.
    expect(glossaryDoc())
        ->toContain('_Avoid_: float, decimal amount, major units')
        ->toContain('_Avoid_: session, current user, auth guard');  // the seam is distinct from the auth guard it reads
});

it('feature-flags doc documents the NS path as the universal fallback (and serialization stays ungated)', function () {
    expect(featureFlagsDoc())
        ->toContain('EXT-1')
        ->toContain('non-serialized (NS)')
        ->toContain('universal fallback')
        ->toContain('serialization workflow');  // ships launch-ready, not gated by EXT-1
});

it('i18n doc documents the PHP-array group-file convention with English per-key fallback', function () {
    expect(i18nDoc())
        ->toContain('PHP-array group files')
        ->toContain('SupportedLocale')          // the typed single source of truth
        ->toContain('per key');                 // English fallback is per key, not per page
});

it('GUIDE.md marks the F1 foundations slice complete (3/3)', function () {
    expect(buildGuide())
        ->toContain('foundations-money-i18n-flags')
        ->toContain('F1 completata 3/3');
});

it('glossary pins are non-vacuous: a non-term has no glossary entry', function () {
    // Same discriminating-pin proof as ModuleTemplateDocsTest's "Warehouse" check — the toContain
    // assertions above are meaningful only if a term NOT in the glossary is absent.
    expect(glossaryDoc())->not->toContain('**Cryptocurrency**');
});

it('reads these docs through a guard that fails loudly on a missing file (non-vacuous)', function () {
    // Re-proves the shared reader for this file's pins: a deleted glossary/convention doc reds the
    // suite rather than passing vacuously (mirrors EventSubstrateDocsTest's "fails loudly" guard).
    expect(fn () => developerDoc('CONTEXT-does-not-exist.md'))
        ->toThrow(RuntimeException::class, 'CONTEXT-does-not-exist.md');
});
