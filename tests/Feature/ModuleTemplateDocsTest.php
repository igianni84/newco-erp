<?php

use App\Modules\Module;

// Pins the module-build template (openspec change foundations-modules-skeleton,
// task 3.1): docs/module-template.md documents the nine decided module
// conventions (design D1–D8) — the falsariga every F2+ module change follows.
// The registry-completeness pin iterates App\Modules\Module, so dropping a
// module from the template's table fails the suite; the remaining pins guard
// the load-bearing conventions (nine sections, public surface, persistence
// prefix, glossary anchor).

function moduleTemplate(): string
{
    return (string) file_get_contents(base_path('docs/module-template.md'));
}

/**
 * Template lines mentioning BOTH the given module name and its spec letter —
 * the registry-completeness mechanism (a module documented *with* its letter,
 * directly mirroring the "Template is registry-complete" scenario). The happy
 * path asserts this is non-empty for every Module case; the failure-ish case
 * asserts it is empty for a name that is not a module.
 *
 * @return list<string>
 */
function templateLinesPairing(string $name, string $letter): array
{
    $lines = explode("\n", moduleTemplate());

    return array_values(array_filter(
        $lines,
        fn (string $line) => str_contains($line, $name) && str_contains($line, $letter),
    ));
}

it('ships the module-build template', function () {
    expect(file_exists(base_path('docs/module-template.md')))->toBeTrue();
});

it('carries all nine module-template sections', function () {
    foreach (range(1, 9) as $n) {
        expect(moduleTemplate())->toContain("## {$n}.");
    }
});

it('is registry-complete — every module appears with its spec letter', function () {
    foreach (Module::cases() as $module) {
        expect(templateLinesPairing($module->name, $module->letter()))
            ->not->toBeEmpty(
                "module-template.md must pair {$module->name} with spec letter {$module->letter()} on one line"
            );
    }
});

it('documents the public surface as Contracts and Events', function () {
    expect(moduleTemplate())
        ->toContain('Contracts')
        ->toContain('Events');
});

it('documents the module-prefixed table convention with the catalog_ example', function () {
    expect(moduleTemplate())->toContain('catalog_');
});

it('anchors terminology to the CONTEXT.md glossary of record', function () {
    expect(moduleTemplate())->toContain('CONTEXT.md');
});

it('registry-completeness check is not vacuous: a non-module name pairs with no line', function () {
    // Warehouse is not one of the nine Module cases; the template must not pair
    // it with a spec letter. Same mechanism as the happy path — proving it can
    // return empty, so the non-empty assertions above are meaningful, not vacuous.
    expect(templateLinesPairing('Warehouse', 'W'))->toBeEmpty();
});
