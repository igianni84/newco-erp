<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\FormatCreated;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\FormatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Format — a standalone PIM reference entity (catalog-product-spine, design D5; product-catalog —
 * Requirement: Format). Wine-display alias: a wine bottle size. It has no parent in the hierarchy
 * and is referenced by exactly one Product Reference (task 3.3, the `(variant, format)` identity).
 *
 * Persistence-only by design (D8): the {@see CreateFormat} action is the
 * sole writer — it assembles the attributes and records {@see FormatCreated}
 * in the same transaction — so `$guarded = []` carries no mass-assignment-from-request risk (the same
 * stance the platform's recorder-only DomainEvent model takes). The lifecycle change extends the action,
 * not this model. Born `draft`; this change defines no transition out of it (design D3).
 *
 * @property int $id
 * @property string $name
 * @property string $size_label
 * @property int $volume_ml
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Format extends Model
{
    /** @use HasFactory<FormatFactory> */
    use HasFactory;

    protected $table = 'catalog_formats';

    /**
     * The Create* action is the only writer; it builds the attribute set internally, so there is no
     * mass-assignment from request input to guard (mirrors the platform DomainEvent model's stance).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Catalog\`), so the model names it explicitly rather than relying on
     * name-based resolution — and the explicit return type lets static analysis infer the factory's
     * model for `Format::factory()->create()`.
     */
    protected static function newFactory(): FormatFactory
    {
        return FormatFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_state' => LifecycleState::class,
            'version' => 'integer',
        ];
    }
}
