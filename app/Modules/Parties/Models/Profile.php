<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profile — the membership in one Club (parties-core, design D2/D4/D8; party-registry — Requirement: Profile —
 * Multi-Profile Membership). The Profile IS the membership (the Netflix-style Customer↔Profile model — there is
 * NO separate Membership entity, § 3); it belongs to EXACTLY ONE Customer and EXACTLY ONE Club (§ 4.2), both
 * required. Both relations are WITHIN-module `belongsTo` (the boundary law forbids only CROSS-module relations —
 * Customer and Club are in the same module): the {@see customer()} and {@see club()} links are required (both
 * non-nullable FKs).
 *
 * Persistence-only by design (D7): the {@see CreateProfile} action is the sole writer — it runs the
 * non-terminal-duplicate pre-check, inserts the row (born `applied`) and records {@see ProfileCreated} in one
 * transaction — so `$guarded = []` carries no mass-assignment-from-request risk. `tier` / `role` are the
 * nullable single-tier / single-role-at-launch attributes (DEC-062); `invited_by_customer_id` is the nullable
 * referral seam (an inviter Customer captured by id, NOT a constrained relation this slice). This change defines
 * no transition out of `applied` (design D2); the multi-profile one-per-(Customer,Club) rule (BR-K-Identity-2)
 * is enforced by the partial unique index on `parties_profiles` plus the action's pre-check.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $club_id
 * @property ProfileState $state
 * @property string|null $tier
 * @property string|null $role
 * @property int|null $invited_by_customer_id
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 * @property-read Club $club
 */
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $table = 'parties_profiles';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The required Customer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module
     * relation ban does not apply). The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The required Club — a WITHIN-module `belongsTo`. The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Profile::factory()->create()`.
     */
    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ProfileState::class,
            'version' => 'integer',
        ];
    }
}
