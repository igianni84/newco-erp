<?php

namespace Tests\Support\Catalog;

use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;

/**
 * Makes a producer KNOWN to Catalog, for tests whose subject is not the producer projection itself.
 *
 * `CreateProductMaster` refuses an id absent from `catalog_producer_states` (AC-0-XM-2;
 * catalog-module-0-completeness-sweep, design D7), so from task 5.2 onward every test that reaches the real
 * creation Action must first say which producer exists. That is a PRECONDITION for most of them, not the
 * thing under test — this fixture states it in one call at the point it matters.
 *
 * IT WRITES THE ROW DIRECTLY, and that is deliberate rather than lazy. The faithful alternative — record a
 * Module-K `ProducerCreated` and let the inline `ProducerLifecycleProjector` upsert (the
 * `lifecycleProjectProducer` idiom, kept where a test is ABOUT the projection) — appends to `domain_events`,
 * and the files this serves count events to the unit (`SpineCreationChainTest` pins exactly seven `*Created`
 * families; `ProductMasterTest` asserts a lone `ProductMasterCreated`). A fixture that perturbs the ledger it
 * is measured against is a worse fixture. The projector's "sole writer" contract is a PRODUCTION claim about
 * `app/`, and a read-model fixture standing outside it is precedented (`ProductMasterCreateConsoleTest` seeded
 * `ProducerState` directly before this class existed).
 *
 * IDEMPOTENT, so it composes with the event path in either order. `last_event_id` is seeded at
 * {@see UNAPPLIED_WATERMARK} = 0 — below every real `domain_events.id` — so a subsequent
 * `ProducerActivated`/`ProducerRetired` STRICTLY advances the watermark and moves the status exactly as it
 * would in production; and a caller that projected a richer status first keeps it, because `firstOrCreate`
 * leaves the existing row alone.
 */
final class ProducerProjectionFixture
{
    /**
     * A watermark below every real `domain_events.id` (the sequence starts at 1), so the projector's
     * strictly-advances guard applies any genuine producer event recorded after this seed.
     */
    private const UNAPPLIED_WATERMARK = 0;

    /**
     * Seed the projection row unless one already exists, and hand the id back so a call site can read
     * `producerId: ProducerProjectionFixture::known(42)` — the precondition inline with the write it enables.
     *
     * The default `registered` is the weakest status that admits Master creation: existence is not activeness,
     * so a test that wants an ACTIVATABLE producer must ask for {@see ProducerProjectionStatus::Active}
     * explicitly and cannot get it by accident.
     */
    public static function known(
        int $producerId,
        ProducerProjectionStatus $status = ProducerProjectionStatus::Registered,
    ): int {
        ProducerState::query()->firstOrCreate(
            ['producer_id' => $producerId],
            ['status' => $status, 'last_event_id' => self::UNAPPLIED_WATERMARK],
        );

        return $producerId;
    }
}
