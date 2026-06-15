<?php

use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Producer supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L6; party-registry —
 * Requirement: Producer Lifecycle). This first slice covers the structural read the retirement cascade walks:
 * the within-module {@see Producer::clubs()} `hasMany` (design L6) — the inverse of {@see Club::producer()},
 * scoped to the Producer's operated Clubs and boundary-clean (both entities are Module K). Later tasks add the
 * ActivateProducer / RetireProducer transition cases here.
 *
 * RefreshDatabase per the task hint; the relation is exercised against a real schema so the `producer_id` FK
 * scope is proven on both engines (SQLite here; PostgreSQL 17 in the cross-engine close — knowledge/testing).
 */
uses(RefreshDatabase::class);

it('exposes the operated Clubs through the within-module clubs() hasMany', function () {
    $producer = Producer::factory()->create();
    Club::factory()->count(3)->create(['producer_id' => $producer->id]);

    // The relation query counts the Producer's Clubs (the read RetireProducer walks — design L6).
    expect($producer->clubs()->count())->toBe(3);

    // The lazy-loaded dynamic property hydrates an Eloquent Collection of Club models.
    expect($producer->clubs)->toBeInstanceOf(Collection::class)
        ->and($producer->clubs)->toHaveCount(3);
    expect($producer->clubs)->each->toBeInstanceOf(Club::class);
});

it('returns an empty collection for a Producer that operates no Clubs', function () {
    $producer = Producer::factory()->create();

    expect($producer->clubs()->count())->toBe(0)
        ->and($producer->clubs)->toBeEmpty();
});

it('scopes clubs() to the owning Producer — Clubs of a different Producer are excluded', function () {
    $producer = Producer::factory()->create();
    $other = Producer::factory()->create();

    Club::factory()->count(2)->create(['producer_id' => $producer->id]);
    Club::factory()->count(3)->create(['producer_id' => $other->id]);

    // The hasMany is keyed on producer_id: each Producer sees only its own Clubs (the cascade must never
    // reach across Producers).
    expect($producer->clubs()->count())->toBe(2)
        ->and($other->clubs()->count())->toBe(3)
        ->and($producer->clubs->pluck('producer_id')->unique()->values()->all())->toBe([$producer->id]);
});
