<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerCreated;
use App\Platform\I18n\TranslatableText;
use App\Platform\I18n\TranslatableTextCast;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ProducerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Producer — the winery identity registry, the source of the producer reference Module 0's Product Master
 * keys off (parties-core, design D2/D4; party-registry — Requirement: Producer Registry). A STANDALONE
 * entity, NOT a Party subtype (§ 4.4), so it carries no party-type marker.
 *
 * Persistence-only by design (D7): the {@see CreateProducer} action is the sole writer — it inserts the row
 * and records {@see ProducerCreated} in one transaction — so `$guarded = []` carries no
 * mass-assignment-from-request risk. Born `draft`; this change defines no transition out of it (design D2),
 * and no auto-created Supplier (BR-K-Producer-3, design D10). The translatable `description` is held as
 * i18n-keyed JSON via {@see TranslatableTextCast} with per-attribute English fallback.
 *
 * @property int $id
 * @property string $name
 * @property string $region
 * @property string|null $appellation
 * @property string $country
 * @property TranslatableText|null $description
 * @property string|null $website
 * @property ProducerStatus $status
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    protected $table = 'parties_producers';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the catalog spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Producer::factory()->create()`.
     */
    protected static function newFactory(): ProducerFactory
    {
        return ProducerFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => TranslatableTextCast::class,
            'status' => ProducerStatus::class,
            'version' => 'integer',
        ];
    }
}
