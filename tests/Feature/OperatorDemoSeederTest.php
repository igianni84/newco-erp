<?php

// RM-07 (docs/validation/Remediation_Tracker.md) — OperatorDemoSeeder provisions the ≥2 distinct operator
// LOGINS the SoD / rejection walkthroughs need (env-readiness ask #2 in docs/validation/README.md). Three
// role-segmented personas (Creator / Reviewer / Approver) on the `operator` guard, so a Creator→Reviewer→
// Approver flow has three distinct actor principals — the SoD floor keys on distinct actor IDENTITY, which a
// single seeded operator can never satisfy. RoleSeeder is the documented precondition (the roles must exist
// before `assignRole` resolves). Demo-only tooling: the bootstrap DatabaseSeeder still provisions only the
// single env-driven operator; these hardcoded-password logins never reach production (DemoSeeder aborts there).

use App\Modules\OperatorPanel\Models\Operator;
use Database\Seeders\OperatorDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    // `assignRole` resolves the demo operators' roles against existing rows — exactly the order DemoSeeder
    // guarantees (RoleSeeder before OperatorDemoSeeder).
    seed(RoleSeeder::class);
});

it('seeds three distinct demo operators that authenticate on the operator guard', function () {
    seed(OperatorDemoSeeder::class);

    assertDatabaseCount('operators', 3);

    foreach (['creator@newco.test', 'reviewer@newco.test', 'approver@newco.test'] as $email) {
        $operator = Operator::query()->where('email', $email)->firstOrFail();

        expect(Hash::check('password', $operator->password))->toBeTrue();

        actingAs($operator, 'operator');
        expect(Auth::guard('operator')->id())->toEqual($operator->getKey());
    }
});

it('grants each demo operator its distinct authority-tier role on the operator guard', function () {
    seed(OperatorDemoSeeder::class);

    $expected = [
        'creator@newco.test' => 'Creator',
        'reviewer@newco.test' => 'Reviewer',
        'approver@newco.test' => 'Approver',
    ];

    foreach ($expected as $email => $role) {
        $operator = Operator::query()->where('email', $email)->firstOrFail();

        expect($operator->getRoleNames()->all())->toBe([$role])
            ->and($operator->roles->pluck('guard_name')->unique()->all())->toBe(['operator']);
    }
});

it('is idempotent — re-running duplicates neither operators nor role grants', function () {
    seed(OperatorDemoSeeder::class);
    seed(OperatorDemoSeeder::class);

    assertDatabaseCount('operators', 3);
    // One role grant per operator — re-seeding attaches no duplicates.
    assertDatabaseCount('model_has_roles', 3);
});
