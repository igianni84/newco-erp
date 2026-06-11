<?php

// Pins the quality-pipeline wiring so a later `composer require` (Filament, Boost)
// cannot silently drop the CLAUDE.md Quality Commands. Composer scripts may be a
// string or an array of lines, so normalise to a single haystack before asserting.

function composerScript(string $name): string
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['scripts'] ?? [])->toHaveKey($name);

    return implode("\n", (array) $composer['scripts'][$name]);
}

it('exposes a Pint-backed format script', function () {
    expect(composerScript('format'))->toContain('pint');
});

it('exposes a Pint-backed lint script that only checks', function () {
    expect(composerScript('lint'))
        ->toContain('pint')
        ->toContain('--test');
});

it('pins the Laravel preset in pint.json', function () {
    $path = base_path('pint.json');

    expect(file_exists($path))->toBeTrue();

    $pint = json_decode((string) file_get_contents($path), true);

    expect($pint['preset'] ?? null)->toBe('laravel');
});
