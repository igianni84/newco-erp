<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateSupplier;
use App\Modules\Parties\Enums\PartyType;
use Carbon\CarbonInterface;
use Database\Factories\Parties\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier — the commercial-counterpart Party subtype, kept deliberately minimal at launch (parties-core,
 * design D1/D4; party-registry — Requirement: Supplier — Minimal Party Subtype). Per § 4.5 it carries only a
 * legal name, the immutable party-type marker (`supplier`), and standard timestamps — no `status`, no
 * `version`, and no commercial-terms / Supplier↔Producer-link attributes (those are Module D's, DEC-067 /
 * DEC-084). A Customer and a Supplier are distinct strongly-typed `parties_*` entities, so BR-K-Identity-5
 * ("a Customer cannot become a Supplier") holds by construction (design D1).
 *
 * Persistence-only by design (D7): the {@see CreateSupplier} action is the sole writer — it inserts the row
 * (fixing `party_type = supplier`) and, unlike the other spine entities, records **no** domain event (the
 * PRD § 15 catalog names none). `$guarded = []` therefore carries no mass-assignment-from-request risk.
 *
 * @property int $id
 * @property string $legal_name
 * @property PartyType $party_type
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $table = 'parties_suppliers';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the catalog and Producer spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Supplier::factory()->create()`.
     */
    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
        ];
    }
}
