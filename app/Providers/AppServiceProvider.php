<?php

namespace App\Providers;

use App\Platform\Events\ConsumerRegistry;
use App\Platform\Features\Features;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The domain-event consumer registry is shared process-wide (foundations-
        // domain-events-audit, design D4): module providers register (event → consumer)
        // pairs into it from boot(), and the delivery path resolves the SAME instance, so
        // it must be a container singleton.
        $this->app->singleton(ConsumerRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the platform's feature-flag definitions with Pennant (design D5).
        // Defining only records in-memory resolvers, so this is safe before migrations.
        Features::define();
    }
}
