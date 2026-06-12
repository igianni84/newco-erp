<?php

use App\Modules\Module;

// Pins the canonical module registry (foundations-modules-skeleton, task 1.1;
// design D2). The enum is the single source the architecture tests iterate, so
// any drift in the nine modules, their spec letters, or their snake_case table
// prefixes must fail here first.

it('enumerates exactly the nine bounded-context modules', function () {
    expect(Module::cases())->toHaveCount(9);
});

it('maps every case to its spec letter per the CLAUDE.md terminology table', function () {
    $letters = [];

    foreach (Module::cases() as $module) {
        $letters[$module->name] = $module->letter();
    }

    expect($letters)->toBe([
        'Catalog' => '0',
        'Parties' => 'K',
        'Allocation' => 'A',
        'Procurement' => 'D',
        'Commerce' => 'S',
        'Inventory' => 'B',
        'Fulfilment' => 'C',
        'Finance' => 'E',
        'OperatorPanel' => 'Admin',
    ]);
});

it('backs every case with its snake_case table prefix', function () {
    $values = [];

    foreach (Module::cases() as $module) {
        $values[$module->name] = $module->value;
    }

    expect($values)->toBe([
        'Catalog' => 'catalog',
        'Parties' => 'parties',
        'Allocation' => 'allocation',
        'Procurement' => 'procurement',
        'Commerce' => 'commerce',
        'Inventory' => 'inventory',
        'Fulfilment' => 'fulfilment',
        'Finance' => 'finance',
        'OperatorPanel' => 'operator_panel',
    ]);
});

it('derives each module namespace as App\Modules\{CaseName}', function () {
    expect(Module::Catalog->namespace())->toBe('App\Modules\Catalog')
        ->and(Module::OperatorPanel->namespace())->toBe('App\Modules\OperatorPanel');
});

it('rejects an unknown module value', function () {
    expect(fn () => Module::from('warehouse'))->toThrow(ValueError::class);
});
