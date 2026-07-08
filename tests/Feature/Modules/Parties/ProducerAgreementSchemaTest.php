<?php

use App\Modules\Parties\Enums\SettlementCadence;
use App\Modules\Parties\Models\Producer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the additive `parties_producer_agreements.settlement_cadence` value-set CHECK
 * (parties-module-k-br-guards task 2.1; RM-22 / MVP-DEC-010; ADR
 * 2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set). The create-table migration left the column a
 * free nullable string (no enumerated domain at the spine slice); migration 2026_07_07_000001 adds the
 * PostgreSQL-only CHECK whose accepted set derives from SettlementCadence::cases() (three tokens —
 * quarterly/monthly/semi_annual — widened with `IS NULL OR` because the D19 seam is nullable). On SQLite the
 * CHECK is skipped and the enum cast is the value-set floor.
 *
 * Both halves of the documented asymmetry are asserted, never skipping the off-lane (mirroring HoldSchemaTest):
 * pgsql rejects a raw out-of-set write by the declared constraint name; SQLite ACCEPTS it (a positive
 * assertion, never a vacuous skip). The forbidden insert is savepoint-wrapped (testing-rule #5) so PG's
 * transaction-abort stays isolated and the follow-up reads remain valid.
 */
uses(RefreshDatabase::class);

it('enforces the settlement-cadence value-set at the PostgreSQL CHECK while SQLite accepts the raw write', function () {
    // A Producer to satisfy the required within-module FK; the raw insert bypasses the model cast so the write
    // reaches the DB CHECK directly (the whole point — the cast would reject `annual` in PHP first).
    $producer = Producer::factory()->create();

    // `annual` is the exact cadence MVP-DEC-010 excludes — out of {quarterly, monthly, semi_annual}, so the
    // test can never pass for the wrong reason.
    $bogus = 'annual';
    $base = [
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => 'draft',
        'version' => 1,
    ];

    // Capture the constraint violation, savepoint-wrapped (testing-rule #5) so PG's transaction-abort stays
    // isolated and the row-count reads after the throw stay valid regardless of the surrounding transaction.
    $violation = '';
    try {
        DB::transaction(fn () => DB::table('parties_producer_agreements')->insert(array_merge($base, [
            'settlement_cadence' => $bogus,
        ])));
    } catch (QueryException $e) {
        $violation = $e->getMessage();
    }

    if (DB::getDriverName() === 'pgsql') {
        // The truth engine: the nullable value-set CHECK rejects the out-of-set token by its declared name; the
        // row never landed, proving the rejected write did not partially apply.
        expect($violation)->toContain('parties_producer_agreements_settlement_cadence_check')
            ->and(DB::table('parties_producer_agreements')->where('settlement_cadence', $bogus)->count())->toBe(0);
    } else {
        // SQLite has no DB CHECK (PG-only) — the raw write bypasses the cast and is accepted (non-vacuous).
        expect($violation)->toBe('')
            ->and(DB::table('parties_producer_agreements')->where('settlement_cadence', $bogus)->count())->toBe(1);
    }

    // Each of the three valid tokens — and NULL (the nullable D19 seam) — inserts on BOTH engines.
    foreach (SettlementCadence::cases() as $cadence) {
        DB::table('parties_producer_agreements')->insert(array_merge($base, [
            'settlement_cadence' => $cadence->value,
        ]));
    }
    DB::table('parties_producer_agreements')->insert(array_merge($base, ['settlement_cadence' => null]));

    expect(DB::table('parties_producer_agreements')->whereIn('settlement_cadence', ['quarterly', 'monthly', 'semi_annual'])->count())->toBe(3)
        ->and(DB::table('parties_producer_agreements')->whereNull('settlement_cadence')->count())->toBe(1);
});
