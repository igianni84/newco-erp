<?php

// Pins the Laravel Boost AI-tooling install (decisions/2026-06-11-stack-versions-
// and-filament-ai-tooling.md): Boost stays a dev-only dependency, its guideline
// output stays in AGENTS.md (never the protected CLAUDE.md), and the committed
// boost.json keeps non-interactive re-runs of boost:install deterministic.

/**
 * @return array<mixed>
 */
function composerDependencies(string $section): array
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    return is_array($composer) && is_array($composer[$section] ?? null)
        ? $composer[$section]
        : [];
}

it('keeps Laravel Boost a dev-only dependency', function () {
    expect(composerDependencies('require-dev'))->toHaveKey('laravel/boost')
        ->and(composerDependencies('require'))->not->toHaveKey('laravel/boost');
});

it('redirects Claude Code guideline output away from the protected CLAUDE.md', function () {
    expect(config('boost.agents.claude_code.guidelines_path'))->toBe('AGENTS.md');
});

it('commits Boost guidelines to AGENTS.md', function () {
    $path = base_path('AGENTS.md');

    expect(file_exists($path))->toBeTrue();

    $guidelines = (string) file_get_contents($path);

    expect($guidelines)
        ->toContain('<laravel-boost-guidelines>')
        ->toContain('</laravel-boost-guidelines>');
});

it('includes the Laravel and Filament guidelines in AGENTS.md', function () {
    $guidelines = (string) file_get_contents(base_path('AGENTS.md'));

    expect($guidelines)
        ->toContain('=== laravel/core rules ===')
        ->toContain('=== filament/filament rules ===');
});

it('pins the Boost agent and package selection in boost.json', function () {
    $path = base_path('boost.json');

    expect(file_exists($path))->toBeTrue();

    $boost = json_decode((string) file_get_contents($path), true);

    $agents = is_array($boost) && is_array($boost['agents'] ?? null) ? $boost['agents'] : [];
    $packages = is_array($boost) && is_array($boost['packages'] ?? null) ? $boost['packages'] : [];

    expect($agents)->toContain('claude_code')
        ->and($packages)->toContain('filament/filament');
});
