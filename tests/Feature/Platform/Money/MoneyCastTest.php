<?php

use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use App\Platform\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
 * MoneyCast proven end-to-end against a real DB row. The cast derives its two backing
 * columns from the attribute key (`amount` → `amount_minor` integer + `amount_currency`
 * ISO code), so this throwaway table follows the F2 `{key}_minor`/`{key}_currency`
 * convention. No production model uses MoneyCast yet — MoneyCastFixture (bottom of file)
 * is the proof. Each read reloads the row from the DB (a fresh model has an empty
 * class-cast cache) so get() actually rebuilds from the raw columns. Raw integer columns
 * come back int (SQLite) or numeric-string (Postgres), so on-disk values are asserted
 * with toEqual (loose) to stay green on both lanes; the object round-trip proves type
 * fidelity exactly.
 */
beforeEach(function () {
    Schema::dropIfExists('money_cast_fixtures');
    Schema::create('money_cast_fixtures', function (Blueprint $table) {
        $table->id();
        $table->integer('amount_minor')->nullable();
        $table->string('amount_currency', 3)->nullable();
    });
});

it('persists a Money as integer minor units + a currency code and rehydrates it equal', function () {
    $eur = Currency::of('EUR');
    $fixture = MoneyCastFixture::create(['amount' => Money::of(1999, $eur)]);

    // On-disk shape: two scalar columns — an integer + an ISO code, never a float or a formatted string.
    expect(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_minor'))->toEqual(1999)
        ->and(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_currency'))->toBe('EUR');

    $amount = MoneyCastFixture::findOrFail($fixture->id)->amount;
    expect($amount)->toBeInstanceOf(Money::class);
    assert($amount instanceof Money); // narrow Money|null for PHPStan max
    expect($amount->equals(Money::of(1999, $eur)))->toBeTrue();
});

it('round-trips a negative amount with the sign preserved (a credit or refund)', function () {
    $eur = Currency::of('EUR');
    $fixture = MoneyCastFixture::create(['amount' => Money::of(-2500, $eur)]);

    expect(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_minor'))->toEqual(-2500);

    $amount = MoneyCastFixture::findOrFail($fixture->id)->amount;
    expect($amount)->toBeInstanceOf(Money::class);
    assert($amount instanceof Money);
    expect($amount->minorUnits)->toBe(-2500)
        ->and($amount->equals(Money::of(-2500, $eur)))->toBeTrue();
});

it('round-trips a zero-exponent currency (JPY) with its code preserved', function () {
    $jpy = Currency::of('JPY');
    $fixture = MoneyCastFixture::create(['amount' => Money::of(500, $jpy)]);

    expect(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_currency'))->toBe('JPY');

    $amount = MoneyCastFixture::findOrFail($fixture->id)->amount;
    expect($amount)->toBeInstanceOf(Money::class);
    assert($amount instanceof Money);
    expect($amount->currency)->toBe(Currency::JPY)
        ->and($amount->equals(Money::of(500, $jpy)))->toBeTrue();
});

it('represents a null money as null columns and rehydrates as null', function () {
    $fixture = MoneyCastFixture::create(['amount' => null]);

    expect(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_minor'))->toBeNull()
        ->and(DB::table('money_cast_fixtures')->where('id', $fixture->id)->value('amount_currency'))->toBeNull();

    expect(MoneyCastFixture::findOrFail($fixture->id)->amount)->toBeNull();
});

it('rejects assigning a non-Money value (fail-closed)', function () {
    expect(fn () => (new MoneyCastFixture)->setAttribute('amount', 'not-money'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a corrupt non-numeric minor-units column on read (fail-closed)', function () {
    $cast = new MoneyCast;

    // Bypass the schema to feed get() a corrupt raw column — a numeric-typed column could
    // never hold this, but a wrong cast key or a manual data fix could, and money must fail loud.
    expect(fn () => $cast->get(new MoneyCastFixture, 'amount', null, [
        'amount_minor' => 'not-a-number',
        'amount_currency' => 'EUR',
    ]))->toThrow(InvalidArgumentException::class);
});

/**
 * Throwaway model exercising {@see MoneyCast} against the `money_cast_fixtures` table.
 *
 * @property int $id
 * @property Money|null $amount
 */
class MoneyCastFixture extends Model
{
    protected $table = 'money_cast_fixtures';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
        ];
    }
}
