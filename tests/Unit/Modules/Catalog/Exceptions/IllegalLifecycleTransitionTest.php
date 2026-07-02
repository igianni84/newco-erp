<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use Tests\TestCase;

// Pins the single parameterized lifecycle-transition guard exception (catalog-lifecycle-approval,
// task 2.1; design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine). The shared
// transition mechanism (task 2.2+) throws it on an out-of-state call for ANY of the seven spine
// entities — one class, the entity name a parameter, because the FSM is uniform. Here we assert each
// named factory builds the right class with a localized, PII-free reason that names BOTH the offending
// state and the entity. Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the
// translator available so __() resolves the lang/en/catalog.php copy instead of echoing the key back.

uses(TestCase::class);

// For each factory the chosen from-state's token is ABSENT from that key's literal template (the copy
// names a DIFFERENT valid-from state), so the token's presence in the message proves :state was
// interpolated — not merely that the copy spells a similar word. The entity token ('ProductMaster')
// appears in no template either, so its presence proves :entity was interpolated.

it('rejects submitting an entity that is not draft, naming the offending state and entity', function () {
    $exception = IllegalLifecycleTransition::cannotSubmit(LifecycleState::Active, 'ProductMaster');

    expect($exception)->toBeInstanceOf(IllegalLifecycleTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('active')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects activating an entity that is not reviewed, naming the offending state and entity', function () {
    $exception = IllegalLifecycleTransition::cannotActivate(LifecycleState::Retired, 'ProductMaster');

    expect($exception)->toBeInstanceOf(IllegalLifecycleTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('retired')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects retiring an entity that is not active, naming the offending state and entity', function () {
    $exception = IllegalLifecycleTransition::cannotRetire(LifecycleState::Draft, 'ProductMaster');

    expect($exception)->toBeInstanceOf(IllegalLifecycleTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('draft')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects reopening an entity that is not retired, naming the offending state and entity', function () {
    $exception = IllegalLifecycleTransition::cannotReopen(LifecycleState::Draft, 'ProductMaster');

    expect($exception)->toBeInstanceOf(IllegalLifecycleTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('draft')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects re-submitting an entity that is not reviewed, naming the offending state and entity', function () {
    // Re-submit (RM-06) is the twin of reject — valid only from reviewed; a draft entity has nothing to
    // re-submit. 'draft' is absent from the cannot_resubmit template, so its presence proves interpolation.
    $exception = IllegalLifecycleTransition::cannotResubmit(LifecycleState::Draft, 'ProductMaster');

    expect($exception)->toBeInstanceOf(IllegalLifecycleTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('draft')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('resolves every lifecycle transition lang key with the :state and :entity placeholders wired', function (string $key) {
    // Neither sentinel appears in any literal template, so the presence of BOTH in the resolved
    // string proves each placeholder was interpolated; a missing key would make Laravel echo the key
    // back unchanged.
    $resolved = __($key, ['state' => 'somestate', 'entity' => 'SomeEntity']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('somestate')
        ->and($resolved)->toContain('SomeEntity');
})->with([
    'catalog.lifecycle.cannot_submit',
    'catalog.lifecycle.cannot_activate',
    'catalog.lifecycle.cannot_retire',
    'catalog.lifecycle.cannot_reopen',
    'catalog.lifecycle.cannot_resubmit',
]);

it('preserves the existing catalog creation-rejection lang groups', function () {
    // The lifecycle group is ADDED alongside the catalog-product-spine creation guards — not a rewrite;
    // the three pre-existing keys must still resolve (acceptance: existing groups preserved).
    expect(__('catalog.product_master.duplicate_identity', ['name' => 'X', 'appellation' => 'Y', 'producer' => 7]))
        ->not->toBe('catalog.product_master.duplicate_identity');

    expect(__('catalog.product_master.unsupported_product_type', ['type' => 'BEER']))
        ->not->toBe('catalog.product_master.unsupported_product_type')
        ->toContain('BEER');

    expect(__('catalog.composite_sku.insufficient_constituents', ['count' => 1]))
        ->not->toBe('catalog.composite_sku.insufficient_constituents')
        ->toContain('1');
});
