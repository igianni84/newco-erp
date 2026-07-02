<?php

use App\Modules\Parties\Console\ScanEnhancedKycThresholds;
use App\Platform\Events\Demo\DemoCommand;
use App\Platform\Events\SweepCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Console commands live beside their concern (App\Platform\*, App\Modules\*\Console) rather than in
    // app/Console/Commands — the only path Laravel's auto-discovery scans — so they are registered explicitly here.
    ->withCommands([
        SweepCommand::class,
        DemoCommand::class,
        ScanEnhancedKycThresholds::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
