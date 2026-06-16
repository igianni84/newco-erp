<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Consumers\ProducerLifecycleProjector;
use App\Platform\Events\ConsumerRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Catalog module service provider — the standard wiring seam (design D1,
 * foundations-modules-skeleton). Routes, translations, event listeners and
 * container bindings for this bounded context land here as the module grows.
 *
 * As of catalog-lifecycle-approval (task 1.2; design D4) this is the FIRST
 * module provider to register a domain-event consumer: it binds the
 * {@see ProducerLifecycleProjector} to the Module K supply-side lifecycle
 * events on the shared {@see ConsumerRegistry}, establishing the production
 * "{Module}ServiceProvider::boot() is the consumer-registration seam" pattern.
 */
class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     *
     * The injected {@see ConsumerRegistry} is the container singleton the
     * DomainEventRecorder fans out against (bound in AppServiceProvider), so
     * registrations made here are visible to every emitting transaction. Without
     * this wiring `ProducerActivated`/`ProducerRetired` would fan out zero
     * deliveries and the producer-state projection — hence the activation gate —
     * would never update (design D4 risk note). Registration is idempotent (the
     * registry de-dupes a repeated (event, consumer) pair); inline delivery mode
     * is the launch substrate, so no queue ADR gate is crossed.
     */
    public function boot(ConsumerRegistry $registry): void
    {
        $registry->register(ProducerLifecycleProjector::PRODUCER_ACTIVATED, ProducerLifecycleProjector::class);
        $registry->register(ProducerLifecycleProjector::PRODUCER_RETIRED, ProducerLifecycleProjector::class);
    }
}
