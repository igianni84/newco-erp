<?php

use App\Modules\Module;
use Illuminate\Database\Eloquent\Model;

// Pins the module persistence convention (foundations-modules-skeleton, task 2.4;
// design D6 — "persistence conventions"). Every Eloquent model under
// App\Modules\** must declare an explicit $table whose name starts with its
// owning module's snake_case prefix — the Module enum's backing value + '_'
// (e.g. catalog_product_masters, parties_holds). Eloquent cannot guess a
// prefixed name, so the explicitness is the point: table ownership is readable
// in every query and migration, making invariant 10 (no cross-module DB access)
// visible at the SQL layer. This is the forward edge of the boundary law for
// the persistence dimension (delta-spec "Module Persistence Conventions").
//
// FORWARD-BINDING / PROVEN-EMPTY: zero module models exist today, so the test
// must pass by a scan that demonstrably RAN — never by a silent error or skip
// (delta-spec scenario "Empty model set is handled honestly"). The proof is the
// non-empty file scan asserted at the end: a broken path or glob would collect
// nothing and make the law vacuous, so we first prove the walk saw real module
// code (today the nine service providers + the panel provider), then assert the
// law over the — currently empty — set of module models. The instant the first
// model lands it is classified and its $table checked (scenario "Module model
// declares its prefixed table"); the three red-proofs in this task's progress
// note exercise exactly that.
//
// Boot-free by design (D8): the modules root is located by reflecting the
// registry's own file — no Laravel container needed. The walk is driven by
// Module::cases() (never a hardcoded list): each module owns the subtree under
// its own directory, so a file's owning module — and thus its required table
// prefix (Module->value.'_') — is known structurally, with no path-segment
// parsing. The $table default is read reflectively via getDefaultProperties():
// verified against vendor that the base Illuminate\Database\Eloquent\Model
// declares `protected $table;`, so the key is always present with a null default
// when a subclass does not override it — null is not a string, hence a violation.

it('requires every module Eloquent model to declare its module-prefixed table', function () {
    $modulesRoot = dirname((string) (new ReflectionClass(Module::class))->getFileName());

    $scannedFiles = [];
    $violations = [];

    foreach (Module::cases() as $module) {
        $moduleDir = $modulesRoot.'/'.$module->name;

        if (! is_dir($moduleDir)) {
            continue; // conformance test (2.1) guarantees the directory; defensive only
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $scannedFiles[] = $fileInfo->getPathname();

            // path -> FQCN, rooted at the module's own namespace (registry-derived,
            // no App\ hardcoding): App\Modules\{Module}\{sub\path\Class}.
            $relative = substr($fileInfo->getPathname(), strlen($moduleDir) + 1);
            $fqcn = $module->namespace().'\\'.str_replace('/', '\\', substr($relative, 0, -4));

            if (! class_exists($fqcn)) {
                continue; // interfaces, traits, enums, PSR-4 mismatches — not models
            }

            $reflection = new ReflectionClass($fqcn);

            if (! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                continue; // only concrete Eloquent models carry the table convention
            }

            $table = $reflection->getDefaultProperties()['table'] ?? null;
            $requiredPrefix = $module->value.'_';

            if (! is_string($table) || ! str_starts_with($table, $requiredPrefix)) {
                $violations[] = sprintf(
                    '%s must declare $table starting with "%s" (got %s)',
                    $fqcn,
                    $requiredPrefix,
                    is_string($table) ? '"'.$table.'"' : 'none',
                );
            }
        }
    }

    // Proven-empty, not vacuous: the walk must have seen real module PHP files.
    // If this were empty the scan never ran and the law below would be
    // meaningless — so the honesty of the empty model set rests on this line.
    expect($scannedFiles)->not->toBeEmpty();

    // The law: no module Eloquent model breaks the module-prefixed-$table
    // convention. Empty today (no models exist); binds forward as models arrive.
    expect($violations)->toBe([]);
});
