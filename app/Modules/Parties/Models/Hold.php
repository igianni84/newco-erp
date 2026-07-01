<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Platform\Events\ActorRole;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\HoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hold — NewCo's unified, trigger-agnostic account-restriction primitive (parties-holds, design L1/L3;
 * party-registry — Requirement: Hold Registry, Hold Lifecycle and Lift Discipline). A Hold carries a
 * {@see HoldType} (one of the eight types — `admin/kyc/payment/fraud/compliance/credit` + the two DEC-008
 * finance-driven types `chargeback_review/storage_payment_failed`), a polymorphic scope (`scope_type`
 * {@see HoldScope} + a `scope_id` within-module reference, no DB FK — design L1), an `active | lifted`
 * lifecycle ({@see HoldStatus}, born `active`), and placement + lift audit metadata. Module K is the
 * registry-of-record (DEC-168); a scope may carry multiple concurrent `active` Holds (BR-K-Hold-1).
 *
 * Persistence-only by design (design L3): the `PlaceHold` and `LiftHold` Actions are the sole writers (added in
 * tasks 3.1/3.2) — each runs one `DB::transaction`, resolves the actor from `ActorContext`, and records its
 * `CustomerHoldPlaced` / `CustomerHoldLifted` event in the same transaction — so `$guarded = []`
 * carries no mass-assignment-from-request risk. The model holds NO Eloquent relation: the polymorphic scope has
 * no FK, and exposing the scoped Customer/Account/Profile would cross no boundary here but is unnecessary (the
 * read-API resolves the scope by id). The `reason` / `lift_reason` are controlled business strings (design L5),
 * never PII; system-placed Holds carry a NULL `reason`.
 *
 * @property int $id
 * @property HoldType $hold_type
 * @property HoldScope $scope_type
 * @property int $scope_id
 * @property HoldStatus $status
 * @property string|null $reason
 * @property ActorRole $placed_actor_role
 * @property int|null $placed_actor_id
 * @property string|null $lift_reason
 * @property ActorRole|null $lifted_actor_role
 * @property int|null $lifted_actor_id
 * @property CarbonImmutable|null $lifted_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Hold extends Model
{
    /** @use HasFactory<HoldFactory> */
    use HasFactory;

    protected $table = 'parties_holds';

    /**
     * The PlaceHold / LiftHold actions are the only writers; each assembles the attributes internally, so there
     * is no mass-assignment from request input to guard (mirrors the sibling Parties models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Hold::factory()->create()`.
     */
    protected static function newFactory(): HoldFactory
    {
        return HoldFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hold_type' => HoldType::class,
            'scope_type' => HoldScope::class,
            // scope_id / the actor ids are cast to integer so they read back as PHP ints on BOTH engines (an
            // uncast bigint reads back as a numeric STRING on PostgreSQL — knowledge/testing/rules.md #6).
            'scope_id' => 'integer',
            'status' => HoldStatus::class,
            'placed_actor_role' => ActorRole::class,
            'placed_actor_id' => 'integer',
            'lifted_actor_role' => ActorRole::class,
            'lifted_actor_id' => 'integer',
            'lifted_at' => 'immutable_datetime',
        ];
    }
}
