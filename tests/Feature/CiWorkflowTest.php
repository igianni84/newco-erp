<?php

// Pins the CI contract (openspec change bootstrap-laravel-app, platform spec
// "Quality Pipeline"): the workflow runs on every push and pull request, on the
// local PHP minor, with a composer cache, and runs the lint → type_check → test
// gates exactly as the CLAUDE.md Quality Commands define them — an edit that
// drops or reorders a gate fails the suite loudly.

function ciWorkflow(): string
{
    return (string) file_get_contents(base_path('.github/workflows/ci.yml'));
}

function ciGatePosition(string $step): int
{
    $position = strpos(ciWorkflow(), $step);

    if (! is_int($position)) {
        throw new RuntimeException("CI workflow step not found: {$step}");
    }

    return $position;
}

it('commits a CI workflow', function () {
    expect(file_exists(base_path('.github/workflows/ci.yml')))->toBeTrue();
});

it('triggers CI on every push and pull request', function () {
    expect(ciWorkflow())
        ->toContain('push:')
        ->toContain('pull_request:');
});

it('runs CI on the local PHP minor version', function () {
    expect(ciWorkflow())->toContain("php-version: '8.5'");
});

it('caches composer downloads in CI', function () {
    expect(ciWorkflow())
        ->toContain('actions/cache@')
        ->toContain('composer config cache-files-dir');
});

it('runs the CI gates in Quality Commands order: lint, type_check, test', function () {
    // Pin the executable `run:` lines, not bare command strings — those can
    // legitimately appear in workflow comments too.
    expect(ciWorkflow())
        ->toContain('run: vendor/bin/pint --test')
        ->toContain('run: vendor/bin/phpstan analyse')
        ->toContain('run: php artisan test');

    expect(ciGatePosition('run: vendor/bin/pint --test'))
        ->toBeLessThan(ciGatePosition('run: vendor/bin/phpstan analyse'))
        ->and(ciGatePosition('run: vendor/bin/phpstan analyse'))
        ->toBeLessThan(ciGatePosition('run: php artisan test'));
});
