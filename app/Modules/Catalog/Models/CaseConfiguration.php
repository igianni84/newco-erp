<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationCreated;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\CaseConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Case Configuration — a standalone PIM reference entity (catalog-product-spine, design D5;
 * product-catalog — Requirement: Case Configuration). Distinct from Format: it carries packaging-form
 * attributes only (units per case, packaging type) and is referenced by a Sellable SKU (Intrinsic), the
 * only SKU shape that references one (task 4.1, BR-SKU-1).
 *
 * It carries NO breakability flag (BR-RefData-2): whether a case may be split at sale is the layered
 * breakability rule decided downstream in Module A (Layer 2) / Module S (Layer 3) — never a property
 * here. The table simply has no such column.
 *
 * Persistence-only by design (D8): the {@see CreateCaseConfiguration} action is the sole writer — it
 * assembles the attributes and records {@see CaseConfigurationCreated} in the same transaction — so
 * `$guarded = []` carries no mass-assignment-from-request risk. The lifecycle change extends the action,
 * not this model. Born `draft`; this change defines no transition out of it (design D3).
 *
 * @property int $id
 * @property string $name
 * @property int $units_per_case
 * @property string $packaging_type
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class CaseConfiguration extends Model
{
    /** @use HasFactory<CaseConfigurationFactory> */
    use HasFactory;

    protected $table = 'catalog_case_configurations';

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
     * model for `CaseConfiguration::factory()->create()`.
     */
    protected static function newFactory(): CaseConfigurationFactory
    {
        return CaseConfigurationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_state' => LifecycleState::class,
            'units_per_case' => 'integer',
            'version' => 'integer',
        ];
    }
}
