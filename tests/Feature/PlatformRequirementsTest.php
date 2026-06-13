<?php

// Guards the tech-stack floors mandated by CLAUDE.md ("Tech Stack and Tech Rules"):
// PHP >= 8.5 and Laravel 13.x (^13.0). Floor checks, not exact pins, so patch/minor
// bumps don't break the suite — but a drift below the supported baseline fails loudly.

it('runs on a supported PHP version (>= 8.5 per CLAUDE.md tech rules)', function () {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80500);
});

it('runs on Laravel 13.x per CLAUDE.md tech rules (^13.0)', function () {
    $version = app()->version();

    expect(version_compare($version, '13.0.0', '>='))->toBeTrue()
        ->and(version_compare($version, '14.0.0', '<'))->toBeTrue();
});
