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

    // strpos finds the FIRST occurrence — the SQLite (`quality`) lane's test line,
    // which sits after pint/phpstan there. The pgsql lane (below) adds a SECOND
    // `php artisan test` line in a later job; it does not disturb this ordering.
    expect(ciGatePosition('run: vendor/bin/pint --test'))
        ->toBeLessThan(ciGatePosition('run: vendor/bin/phpstan analyse'))
        ->and(ciGatePosition('run: vendor/bin/phpstan analyse'))
        ->toBeLessThan(ciGatePosition('run: php artisan test'));
});

it('runs a second test lane on PostgreSQL 17 (the production-DB ADR floor)', function () {
    // foundations-domain-events-audit design D8: a separate `tests-pgsql` job re-runs
    // the test gate against the engine that ships, so the Postgres-truthful migration
    // branches the SQLite dev lane can't reach — the `actor_role` CHECK, the partial
    // pending index, the plpgsql immutability triggers — are proven on Postgres.
    expect(ciWorkflow())
        ->toContain('tests-pgsql:')
        ->toContain('image: postgres:17')
        ->toContain('pdo_pgsql')
        ->toContain('DB_CONNECTION: pgsql')
        ->toContain('name: Tests (Pest on PostgreSQL 17)');
});

it('runs the engine-independent gates exactly once and the test gate in both lanes', function () {
    // Lint and PHPStan are engine-independent (design D8): they run ONCE, in the
    // SQLite lane. This is the failure-ish guard — were they duplicated into the
    // pgsql job (e.g. via a careless matrix), these counts would break.
    expect(substr_count(ciWorkflow(), 'run: vendor/bin/pint --test'))->toBe(1);
    expect(substr_count(ciWorkflow(), 'run: vendor/bin/phpstan analyse'))->toBe(1);

    // The test gate, by contrast, runs in BOTH lanes — SQLite and Postgres.
    expect(substr_count(ciWorkflow(), 'run: php artisan test'))->toBe(2);
});

it('scopes the pgsql connection switch to the second lane', function () {
    // The SQLite lane stays FIRST so the gate-order pins above anchor on its test
    // line; the `DB_CONNECTION: pgsql` switch lives inside the second job, never
    // leaking into the first (which would silently move the whole suite off SQLite).
    expect(ciGatePosition('name: Tests (Pest on in-memory SQLite)'))
        ->toBeLessThan(ciGatePosition('tests-pgsql:'))
        ->and(ciGatePosition('tests-pgsql:'))
        ->toBeLessThan(ciGatePosition('DB_CONNECTION: pgsql'));
});
