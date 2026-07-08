<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Actions\RequireProducerKyc;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Exceptions\ProducerReviewGovernedContentLocked;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins BR-K-Producer-5 (interim) — the Producer review-governed content lock (parties-module-k-br-guards task
 * 5.2; design D9; party-registry — Requirement: Producer Review-Governed Content Lock). The model-level
 * `updating` guard (the RM-24 immutability pattern, Catalog's `ProductMaster::booted()`)
 * makes `name`/`description`/`region`/`website` immutable once the PERSISTED status is `active`, while a `draft`
 * sets them freely and every status/kyc-only transition (activation, retirement, KYC) passes untouched — the
 * design R5 no-false-positive floor.
 *
 * The throws-tests drive the dirty write past the casts via `forceFill` (the ProductMaster RM-24 idiom). The
 * no-false-positive tests drive the REAL transition Actions so the lock is proven against the actual
 * `$producer->update(['status'|'kyc_status' => …])` writers, not a synthetic update: retirement + KYC run on a
 * factory-born `active` Producer (persisted `active`, the meaningful case where only the `isDirty(content)`
 * conjunct spares them), and activation runs the real CreateProducer + ActivateProducer SoD flow.
 */
uses(RefreshDatabase::class);

it('locks a review-governed string field on an active Producer, leaving content and status unchanged', function (string $field) {
    $producer = Producer::factory()->create([
        'status' => ProducerStatus::Active,
        $field => 'Original value',
    ]);

    // forceFill drives the new value THROUGH the cast (the RM-24 forceFill idiom) so the attribute is genuinely
    // dirty; the `updating` guard fires BEFORE the UPDATE SQL, so nothing persists.
    $producer->forceFill([$field => 'Reworded value']);

    expect(fn () => $producer->save())
        ->toThrow(ProducerReviewGovernedContentLocked::class);

    // The guard fails closed ahead of the UPDATE — the persisted content and status are untouched.
    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->getAttribute($field))->toBe('Original value')
        ->and($fresh->status)->toBe(ProducerStatus::Active);
})->with(array_values(array_diff(Producer::REVIEW_GOVERNED_FIELDS, ['description'])));

it('names exactly the four canon review-governed fields in the shared REVIEW_GOVERNED_FIELDS source', function () {
    // The guard and this suite share ONE source (the docblock's no-magic-list-drift claim): the string-field
    // dataset above derives from the const (a field added there is auto-covered), and this pin makes editing
    // the const itself a conscious act — the four fields are the canon AC-K-BR-Producer-5 set.
    expect(Producer::REVIEW_GOVERNED_FIELDS)->toBe(['name', 'description', 'region', 'website']);
});

it('locks the review-governed description (a translatable cast) on an active Producer', function () {
    $producer = Producer::factory()->create([
        'status' => ProducerStatus::Active,
        'description' => TranslatableText::of(['en' => 'Original story']),
    ]);

    $producer->forceFill(['description' => TranslatableText::of(['en' => 'Reworded story'])]);

    expect(fn () => $producer->save())
        ->toThrow(ProducerReviewGovernedContentLocked::class);

    // Re-fetch THROUGH the cast (never a byte-compare of stored JSON — PG jsonb reorders keys): unchanged.
    expect(Producer::findOrFail($producer->id)->description?->resolve('en'))->toBe('Original story');
});

it('lets a draft Producer set its review-governed content freely (the lock applies only while active)', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft]);

    $producer->forceFill([
        'name' => 'Domaine Renamed',
        'region' => 'Côte de Nuits',
        'website' => 'https://renamed.example',
        'description' => TranslatableText::of(['en' => 'A freshly written story']),
    ]);

    // A draft is pre-publication — the content set persists with no throw (the guard gates on persisted `active`).
    $producer->save();

    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->name)->toBe('Domaine Renamed')
        ->and($fresh->region)->toBe('Côte de Nuits')
        ->and($fresh->website)->toBe('https://renamed.example')
        ->and($fresh->description?->resolve('en'))->toBe('A freshly written story');
});

it('passes a retirement of an active Producer — the lock keys on content, not the status transition', function () {
    // The meaningful no-false-positive case: the PERSISTED status IS active, so only the isDirty(content) conjunct
    // keeps the guard from firing. RetireProducer drives $producer->update(['status' => Retired]) (System actor).
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    app(RetireProducer::class)->handle($producer->id);

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired);
});

it('passes a KYC transition on an active Producer — a kyc_status-only update is not review-governed', function () {
    // RequireProducerKyc drives $producer->update(['kyc_status' => Pending]) on an active Producer (persisted
    // active, NULL kyc); the content lock must not false-positive on the kyc_status-only write.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    app(RequireProducerKyc::class)->handle($producer->id);

    expect(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Pending);
});

it('passes activation of a draft Producer — the persisted draft status exempts the write from the lock', function () {
    // Activation is draft → active: the PERSISTED status is draft, so the lock is structurally exempt. Driven
    // through the real CreateProducer (creator 101) + ActivateProducer (distinct approver 202 — the SoD floor) on
    // the KYC-cleared NULL-kyc path, so the actual activation writer $producer->update(['status' => Active]) runs.
    $producer = app(ActorContext::class)->runAs(
        ActorRole::NewcoOps,
        101,
        fn (): Producer => app(CreateProducer::class)->handle(
            name: 'Domaine Leflaive',
            region: 'Burgundy',
            country: 'FR',
        ),
    );

    app(ActorContext::class)->runAs(
        ActorRole::NewcoOps,
        202,
        fn (): Producer => app(ActivateProducer::class)->handle($producer->id),
    );

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);
});
