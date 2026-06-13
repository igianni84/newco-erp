<?php

use App\Platform\I18n\TranslatableText;
use App\Platform\I18n\TranslatableTextCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
 * TranslatableTextCast proven end-to-end against a real DB row. Unlike MoneyCast (two
 * columns), a TranslatableText persists to a SINGLE schema-less JSON column (DEC-064),
 * so this throwaway table has one `label` json column. No production model uses the cast
 * yet — TranslatableTextCastFixture (bottom of file) is the proof. Each read reloads the
 * row from the DB (a fresh model has an empty class-cast cache) so get() actually
 * rehydrates from the raw column. The on-disk value is a JSON string on both lanes
 * (SQLite text + Postgres json), asserted with toEqual after decoding to stay
 * order/whitespace-tolerant; the object round-trip proves entries are preserved exactly.
 */
beforeEach(function () {
    Schema::dropIfExists('translatable_text_cast_fixtures');
    Schema::create('translatable_text_cast_fixtures', function (Blueprint $table) {
        $table->id();
        $table->json('label')->nullable();
    });
});

it('persists a TranslatableText as i18n-keyed JSON and rehydrates it without loss', function () {
    $text = TranslatableText::of(['en' => 'Red Wine', 'it' => 'Vino Rosso', 'ja' => 'ワイン']);
    $fixture = TranslatableTextCastFixture::create(['label' => $text]);

    // On-disk shape: a single JSON column holding the i18n-keyed object (a string at the driver level).
    $raw = DB::table('translatable_text_cast_fixtures')->where('id', $fixture->id)->value('label');
    expect($raw)->toBeString();
    assert(is_string($raw)); // narrow mixed for PHPStan max
    expect(json_decode($raw, true))->toEqual(['en' => 'Red Wine', 'it' => 'Vino Rosso', 'ja' => 'ワイン']);

    $label = TranslatableTextCastFixture::findOrFail($fixture->id)->label;
    expect($label)->toBeInstanceOf(TranslatableText::class);
    assert($label instanceof TranslatableText); // narrow ?TranslatableText for PHPStan max
    expect($label->translations)->toEqual(['en' => 'Red Wine', 'it' => 'Vino Rosso', 'ja' => 'ワイン'])
        ->and($label->resolve('ja'))->toBe('ワイン')        // non-Latin script survives storage
        ->and($label->resolve('it'))->toBe('Vino Rosso')
        ->and($label->resolve('fr'))->toBe('Red Wine');    // per-attribute English fallback survives the round-trip
});

it('represents a null translatable as a null column and rehydrates as null', function () {
    $fixture = TranslatableTextCastFixture::create(['label' => null]);

    expect(DB::table('translatable_text_cast_fixtures')->where('id', $fixture->id)->value('label'))->toBeNull();
    expect(TranslatableTextCastFixture::findOrFail($fixture->id)->label)->toBeNull();
});

it('rejects assigning a non-TranslatableText value (fail-closed)', function () {
    expect(fn () => (new TranslatableTextCastFixture)->setAttribute('label', 'not-translatable'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a corrupt non-string column on read (fail-closed)', function () {
    $cast = new TranslatableTextCast;

    // A json column could never hold an int, but a wrong cast key or a manual data fix could —
    // and translatable content must fail loud rather than silently coerce.
    expect(fn () => $cast->get(new TranslatableTextCastFixture, 'label', 123, []))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * Throwaway model exercising {@see TranslatableTextCast} against the
 * `translatable_text_cast_fixtures` table.
 *
 * @property int $id
 * @property TranslatableText|null $label
 */
class TranslatableTextCastFixture extends Model
{
    protected $table = 'translatable_text_cast_fixtures';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label' => TranslatableTextCast::class,
        ];
    }
}
