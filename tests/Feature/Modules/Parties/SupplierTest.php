<?php

use App\Modules\Parties\Actions\CreateSupplier;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\Supplier;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Supplier — the commercial-counterpart Party subtype, kept deliberately minimal at launch
 * (parties-core task 2.2; design D1/D4/D7/D10; party-registry — Requirement: Supplier — Minimal Party
 * Subtype). It proves the CreateSupplier action persists the Supplier with the immutable `supplier` marker
 * and standard timestamps, that it records NO domain event (the deliberate § 15 event silence, design D7),
 * carries no status/version/commercial-terms columns (§ 4.5 — richer state is Module D's), and never
 * auto-creates a Producer (BR-K-Producer-3, design D10).
 *
 * RefreshDatabase (mirrors ProducerTest): the action opens its own DB::transaction, satisfied by the
 * savepoint under the wrapper.
 */
uses(RefreshDatabase::class);

it('creates a minimal Supplier carrying the immutable supplier marker and timestamps', function () {
    $supplier = app(CreateSupplier::class)->handle(legalName: 'Acme Wines Ltd');

    // Re-fetch so the assertions exercise the read/hydration cast, not the in-memory create() values.
    $read = Supplier::findOrFail($supplier->id);

    expect($read->legal_name)->toBe('Acme Wines Ltd')
        ->and($read->party_type)->toBe(PartyType::Supplier)   // the immutable marker (BR-K-Identity-5)
        ->and($read->created_at)->not->toBeNull()
        ->and($read->updated_at)->not->toBeNull();
});

it('records no domain event — the deliberate Supplier event silence (design D7)', function () {
    app(CreateSupplier::class)->handle(legalName: 'Barossa Cellars Pty');

    // The PRD § 15 event catalog names NO Supplier event; inventing a SupplierCreated would breach spec
    // fidelity. The recorder is never called — the whole domain_events log stays empty.
    expect(DomainEvent::query()->count())->toBe(0);
});

it('is a deliberately minimal entity — no status, version or commercial-terms columns', function () {
    // § 4.5 — the launch Supplier is legal name + marker + timestamps; richer commercial state (Supplier
    // Profile, payment terms, the Supplier↔Producer link) is Module D's, not modelled here. The two spine
    // legs the other parties_* entities carry — `status` and `version` — are deliberately dropped.
    expect(Schema::hasColumn('parties_suppliers', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('parties_suppliers', 'version'))->toBeFalse();
});

it('does not auto-create a Producer (BR-K-Producer-3, design D10)', function () {
    app(CreateSupplier::class)->handle(legalName: 'Tenuta Vineyards Srl');

    // No-auto-cross-create runs both ways: a Supplier never spawns a Producer (the Supplier↔Producer link is
    // Module D's SupplierProducerLink, never modelled in Module K).
    expect(Producer::query()->count())->toBe(0)
        ->and(Supplier::query()->count())->toBe(1);
});

it('produces a Supplier via the factory carrying the supplier marker', function () {
    // The factory is a pure fixture (it bypasses the action); since the action emits no event either, factory
    // and action persist an identical minimal Supplier (the integration close, task 6.2, leans on it).
    $supplier = Supplier::factory()->create();

    expect($supplier->party_type)->toBe(PartyType::Supplier)
        ->and(DomainEvent::query()->count())->toBe(0);
});
