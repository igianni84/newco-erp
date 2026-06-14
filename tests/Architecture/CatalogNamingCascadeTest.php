<?php

use App\Modules\Module;

// Pins the §18 naming cascade as the CANONICAL code naming of the Catalog spine
// (catalog-product-spine, design D7; product-catalog — Requirement: "Naming
// Cascade (category-neutral canonical names)"; spec/02-prd/Module_0_PRD §18;
// spec/03-acceptance/Module_0_Acceptance §6.4 AC-0-GEN-6). The rule has two
// halves, and this file pins both as structure, not prose:
//
//   POSITIVE — the seven category-neutral spine entities and their seven
//   `*Created` events exist under the §18 names (the code identifiers ARE the
//   neutral `Product*`/Format/CaseConfiguration/SellableSku/CompositeSku set).
//
//   NEGATIVE — no Catalog class or event name is a CATEGORY-renamed spine
//   identifier: the former wine-specific structural names (`WineMaster*`,
//   `WineVariant*`, `BottleReference*`) must never appear as a structural or
//   event identifier. "Wine Master", "Wine Variant", "Bottle Reference (BR)"
//   survive ONLY as wine-display aliases in docblocks/CONTEXT.md (pinned by the
//   third test below) — presentation terms, never code identifiers.
//
// Why an ANCHORED prefix (`^Wine` / `^BottleReference`), not a loose `/Wine/`
// substring: the design is a category-neutral CORE + per-type WINE ATTRIBUTE
// tables (design D1 — `catalog_product_master_wine_attributes`,
// `catalog_product_variant_wine_attributes`). Their models,
// `ProductMasterWineAttributes` / `ProductVariantWineAttributes`, legitimately
// carry "Wine" as a SUFFIX qualifier on a category-neutral core — that is the
// §16 representation, not a violation. A substring `/Wine/` would wrongly flag
// them; only a category-PREFIXED spine rename (a `WineMaster`, a
// `BottleReference`) is forbidden. The third test below proves the scan REACHES
// those two suffix-qualified classes and deliberately leaves them clean — so the
// anchoring is exercised, never merely assumed.
//
// Event-vs-model casing divergence is intentional: the two SKU events keep an
// UPPER-case `SKU` (`SellableSKUCreated` / `CompositeSKUCreated`, verbatim per
// §14.1) while their models are lower-`Sku` (`SellableSku` / `CompositeSku`, per
// §18). The event name follows §14.1, the model follows §18; they legitimately
// differ in casing, so both spellings are pinned explicitly.
//
// Boot-free by design (mirrors ModuleConformanceTest / ModulePersistence
// ConventionsTest): the Catalog directory is located by reflecting the registry
// enum's own file — no Laravel container needed — and the negative scan is a
// non-vacuous filesystem walk (the structural identifier of a PSR-4 class IS its
// filename), proven to have seen real Catalog code before the law is asserted.

// The seven category-neutral spine entities (§18 / design D7 / design D5 map).
$canonicalModels = [
    'ProductMaster',
    'ProductVariant',
    'ProductReference',
    'Format',
    'CaseConfiguration',
    'SellableSku',
    'CompositeSku',
];

// The seven `*Created` events, named VERBATIM per §14.1 — note the two SKU
// events keep an UPPER-case `SKU`, diverging from their lower-`Sku` models.
$createdEvents = [
    'ProductMasterCreated',
    'ProductVariantCreated',
    'ProductReferenceCreated',
    'FormatCreated',
    'CaseConfigurationCreated',
    'SellableSKUCreated',
    'CompositeSKUCreated',
];

it('exposes the seven canonical category-neutral spine models and their seven *Created events', function () use ($canonicalModels, $createdEvents) {
    // "The seven" is itself the contract (the spine has exactly these entities);
    // guard the counts so a silent drop from either list is caught, not masked.
    expect($canonicalModels)->toHaveCount(7);
    expect($createdEvents)->toHaveCount(7);

    $modelsNamespace = Module::Catalog->namespace().'\\Models\\';
    $eventsNamespace = Module::Catalog->namespace().'\\Events\\';

    $missing = [];

    foreach ($canonicalModels as $model) {
        $fqcn = $modelsNamespace.$model;
        if (! class_exists($fqcn)) {
            $missing[] = $fqcn;
        }
    }

    foreach ($createdEvents as $event) {
        $fqcn = $eventsNamespace.$event;
        if (! class_exists($fqcn)) {
            $missing[] = $fqcn;
        }
    }

    // Every canonical §18 identifier resolves: the category-neutral names ARE
    // the code (AC-0-GEN-6, positive half).
    expect($missing)->toBe([]);
});

it('uses no Wine*/BottleReference* structural or event identifier anywhere in the Catalog module', function () {
    $modulesRoot = dirname((string) (new ReflectionClass(Module::class))->getFileName());
    $catalogDir = $modulesRoot.'/'.Module::Catalog->name;

    // Collect every Catalog PHP class identifier (PSR-4: the filename IS the
    // class short-name) across the whole subtree — Models/, Events/, Actions/,
    // Enums/, Exceptions/, Providers/ — so "class OR event name" is covered.
    $identifiers = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($catalogDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $fileInfo) {
        if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
            continue;
        }
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }

        $identifiers[] = $fileInfo->getBasename('.php');
    }

    // Non-vacuity, proven two ways: the walk saw real code, AND it specifically
    // reached the two SUFFIX-qualified per-type attribute classes — the exact
    // names that contain "Wine" yet must stay LEGAL (design D1). If the anchored
    // rule below ever regressed to a loose `/Wine/`, these would turn it red, so
    // the allowance is demonstrated, never assumed.
    expect($identifiers)->not->toBeEmpty();
    expect($identifiers)->toContain('ProductMasterWineAttributes');
    expect($identifiers)->toContain('ProductVariantWineAttributes');

    // The law: no category-renamed spine identifier. A forbidden name is one that
    // BEGINS with `Wine` or `BottleReference` (the §18 forbidden set
    // `WineMaster*`/`WineVariant*`/`BottleReference*`, generalised to the prefix);
    // a `*WineAttributes` suffix on a neutral core is explicitly NOT forbidden.
    $violations = array_values(array_filter(
        $identifiers,
        fn (string $name) => preg_match('/^(Wine|BottleReference)/', $name) === 1,
    ));

    expect($violations)->toBe([]);
});

it('retains the wine-display aliases only as documentation on the relevant spine models', function () {
    // The spec keeps "Wine Master"/"Wine Variant"/"Bottle Reference (BR)" alive
    // as wine-display aliases — presentation/documentation terms — never as
    // structural names. The negative test above bars them from code identifiers;
    // this one pins their ONLY legal home: the model docblock. Together they
    // encode "retained ONLY as a wine-display alias" precisely.
    $aliases = [
        'ProductMaster' => 'Wine Master',
        'ProductVariant' => 'Wine Variant',
        'ProductReference' => 'Bottle Reference (BR)',
    ];

    $modelsNamespace = Module::Catalog->namespace().'\\Models\\';

    $missingAlias = [];

    foreach ($aliases as $model => $alias) {
        $fqcn = $modelsNamespace.$model;

        if (! class_exists($fqcn)) {
            $missingAlias[] = $model.' (class missing)';

            continue;
        }

        $docComment = (new ReflectionClass($fqcn))->getDocComment();

        if (! is_string($docComment) || ! str_contains($docComment, $alias)) {
            $missingAlias[] = $model.' → "'.$alias.'"';
        }
    }

    expect($missingAlias)->toBe([]);
});
