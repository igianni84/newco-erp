<?php

use App\Modules\Module;

// Pins the registry <-> filesystem conformance (foundations-modules-skeleton,
// task 2.1; design D2 + D8). The directories directly under app/Modules/ are
// exactly the nine modules named by the canonical registry, and the registry
// enum is the only loose file beside them. The expected set is built from
// Module::cases() — never a hardcoded list — and compared by sorted strict
// equality (set equality, not subset): a missing module directory AND an extra
// tenth directory both fail.
//
// Boot-free by design (D8): the modules root is located by reflecting the
// registry's own file, so this check needs no Laravel container. getFileName()
// is false only for PHP-internal classes; App\Modules\Module is user-defined,
// so it is always the registry's real path. Dot-entries (the . / .. pseudo-
// entries and gitignored OS artifacts such as .DS_Store) are filtered out: they
// are never part of the PSR-4 module structure and design D1 forbids .gitkeep.

it('contains exactly the nine module directories named by the registry', function () {
    $root = dirname((string) (new ReflectionClass(Module::class))->getFileName());

    $directories = array_values(array_filter(
        scandir($root) ?: [],
        fn (string $entry) => ! str_starts_with($entry, '.') && is_dir($root.'/'.$entry),
    ));
    sort($directories);

    $expected = array_map(fn (Module $module) => $module->name, Module::cases());
    sort($expected);

    expect($directories)->toBe($expected);
});

it('keeps the registry enum as the only loose file at the modules root', function () {
    $root = dirname((string) (new ReflectionClass(Module::class))->getFileName());

    $files = array_values(array_filter(
        scandir($root) ?: [],
        fn (string $entry) => ! str_starts_with($entry, '.') && is_file($root.'/'.$entry),
    ));

    expect($files)->toBe(['Module.php']);
});
