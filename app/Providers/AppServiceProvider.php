<?php

namespace App\Providers;

use App\Platform\Events\ActorContext;
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

        // The actor-context seam is shared process-wide (foundations-money-i18n-flags,
        // design D6): a runAs() override must be observed by every emitter that resolves
        // ActorContext within that scope, so the resolved instance must be the SAME one.
        $this->app->singleton(ActorContext::class);
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
