<?php

use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryMode;
use App\Platform\Events\DeliveryStatus;

// Pins the platform substrate enums (foundations-domain-events-audit, task 1.1;
// design D1/D2). The three enums are the typed vocabulary the recorder, ledger
// and registry build on — actor provenance on every envelope (invariant 8), the
// delivery lifecycle, and the launch delivery mode (queued gated behind the
// queue-driver ADR, F4–F6). Each case/value map is asserted verbatim and
// order-sensitive, mirroring ModuleTest: any drift in a case or its persisted
// token must fail here first.

it('backs ActorRole with the four spec actor roles', function () {
    $values = [];

    foreach (ActorRole::cases() as $role) {
        $values[$role->name] = $role->value;
    }

    expect($values)->toBe([
        'NewcoOps' => 'newco_ops',
        'Producer' => 'producer',
        'Customer' => 'customer',
        'System' => 'system',
    ]);
});

it('backs DeliveryStatus with the three ledger states', function () {
    $values = [];

    foreach (DeliveryStatus::cases() as $status) {
        $values[$status->name] = $status->value;
    }

    expect($values)->toBe([
        'Pending' => 'pending',
        'Done' => 'done',
        'Failed' => 'failed',
    ]);
});

it('exposes Inline as the only delivery mode until the queue ADR lands', function () {
    $values = [];

    foreach (DeliveryMode::cases() as $mode) {
        $values[$mode->name] = $mode->value;
    }

    expect($values)->toBe([
        'Inline' => 'inline',
    ]);
});

it('rejects an actor role outside the spec set', function () {
    expect(fn () => ActorRole::from('admin'))->toThrow(ValueError::class);
});
