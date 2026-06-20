<?php

// Task 1.1 (operator-console-catalog-master; ADR 2026-06-19) — the operator console founds the
// `operator-console` capability by hosting its Filament resources/pages/widgets INSIDE the
// OperatorPanel module, not in the shell's default app/Filament/**. These assertions pin that the
// admin panel's discovery is repointed to app/Modules/OperatorPanel/Filament/** with the matching
// App\Modules\OperatorPanel\Filament\* namespaces, and that the panel still boots + renders the
// dashboard for an authenticated operator after the repoint (the auth surface from
// operator-auth-foundation stays green — see tests/Feature/OperatorPanelTest.php).

use App\Modules\OperatorPanel\Models\Operator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('discovers resources, pages and widgets from the OperatorPanel module namespace', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getResourceNamespaces())->toContain('App\Modules\OperatorPanel\Filament\Resources')
        ->and($panel->getPageNamespaces())->toContain('App\Modules\OperatorPanel\Filament\Pages')
        ->and($panel->getWidgetNamespaces())->toContain('App\Modules\OperatorPanel\Filament\Widgets');
});

it('points discovery directories at the OperatorPanel module path', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getResourceDirectories())->toContain(app_path('Modules/OperatorPanel/Filament/Resources'))
        ->and($panel->getPageDirectories())->toContain(app_path('Modules/OperatorPanel/Filament/Pages'))
        ->and($panel->getWidgetDirectories())->toContain(app_path('Modules/OperatorPanel/Filament/Widgets'));
});

it('no longer discovers from the abandoned app/Filament shell location', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getResourceNamespaces())->not->toContain('App\Filament\Resources')
        ->and($panel->getPageNamespaces())->not->toContain('App\Filament\Pages')
        ->and($panel->getWidgetNamespaces())->not->toContain('App\Filament\Widgets');
});

it('still boots and renders the dashboard for an authenticated operator after the repoint', function () {
    actingAs(Operator::factory()->create(), 'operator');

    get('/admin')->assertOk();
});
