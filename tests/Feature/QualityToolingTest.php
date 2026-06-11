<?php

// Pins the quality-pipeline wiring so a later `composer require` (Filament, Boost)
// cannot silently drop the CLAUDE.md Quality Commands. Composer scripts may be a
// string or an array of lines, so normalise to a single haystack before asserting.

function composerScript(string $name): string
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    $scripts = is_array($composer) && is_array($composer['scripts'] ?? null)
        ? $composer['scripts']
        : [];

    expect($scripts)->toHaveKey($name);

    $value = $scripts[$name] ?? [];
    $lines = array_filter(is_array($value) ? $value : [$value], 'is_string');

    return implode("\n", $lines);
}

it('exposes a Pint-backed format script', function () {
    expect(composerScript('format'))->toContain('pint');
});

it('exposes a Pint-backed lint script that only checks', function () {
    expect(composerScript('lint'))
        ->toContain('pint')
        ->toContain('--test');
});

it('exposes an Artisan-backed test script', function () {
    expect(composerScript('test'))->toContain('artisan test');
});

it('exposes a PHPStan-backed analyse script', function () {
    expect(composerScript('analyse'))
        ->toContain('phpstan')
        ->toContain('analyse');
});

it('pins the Laravel preset in pint.json', function () {
    $path = base_path('pint.json');

    expect(file_exists($path))->toBeTrue();

    $pint = json_decode((string) file_get_contents($path), true);

    $preset = is_array($pint) ? ($pint['preset'] ?? null) : null;

    expect($preset)->toBe('laravel');
});
