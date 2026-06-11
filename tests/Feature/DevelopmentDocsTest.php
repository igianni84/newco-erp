<?php

// Pins the developer guide (openspec change bootstrap-laravel-app, task 3.4):
// docs/development.md documents the five CLAUDE.md Quality Commands verbatim,
// the ralph loop, the agent-facing Filament docs index, the AGENTS.md
// regeneration command, and a version table cross-checked against
// composer.lock — so a composer update that drifts from the documented
// snapshot fails the suite until the table is refreshed.

function developmentGuide(): string
{
    return (string) file_get_contents(base_path('docs/development.md'));
}

function lockedPackageVersion(string $package): string
{
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);

    foreach (['packages', 'packages-dev'] as $section) {
        $entries = is_array($lock) && is_array($lock[$section] ?? null) ? $lock[$section] : [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $version = $entry['version'] ?? null;

            if (($entry['name'] ?? null) === $package && is_string($version)) {
                return ltrim($version, 'v');
            }
        }
    }

    throw new RuntimeException("Package not found in composer.lock: {$package}");
}

it('ships the development guide', function () {
    expect(file_exists(base_path('docs/development.md')))->toBeTrue();
});

it('documents the five Quality Commands verbatim', function () {
    expect(developmentGuide())
        ->toContain('vendor/bin/pint')
        ->toContain('php artisan test --filter={name}')
        ->toContain('php artisan test')
        ->toContain('vendor/bin/phpstan analyse')
        ->toContain('vendor/bin/pint --test');
});

it('documents how to run and monitor the ralph loop', function () {
    expect(developmentGuide())
        ->toContain('./ralph.sh')
        ->toContain('progress.md')
        ->toContain('RALPH_EFFORT');
});

it('links the agent-facing Filament docs index and the AGENTS.md regeneration command', function () {
    expect(developmentGuide())
        ->toContain('https://filamentphp.com/docs/llms.txt')
        ->toContain('boost:install --guidelines');
});

it('records the exact installed versions from composer.lock, Boost included', function () {
    $packages = [
        'laravel/framework',
        'filament/filament',
        'laravel/boost',
        'pestphp/pest',
        'pestphp/pest-plugin-laravel',
        'phpstan/phpstan',
        'larastan/larastan',
        'laravel/pint',
    ];

    foreach ($packages as $package) {
        expect(developmentGuide())
            ->toContain($package)
            ->toContain(lockedPackageVersion($package));
    }
});

it('fails loudly when a documented package is missing from composer.lock', function () {
    expect(fn () => lockedPackageVersion('vendor/does-not-exist'))
        ->toThrow(RuntimeException::class, 'vendor/does-not-exist');
});
