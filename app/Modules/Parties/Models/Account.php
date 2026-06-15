<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use Carbon\CarbonInterface;
use Database\Factories\Parties\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Account — the per-Customer transactional/billing container, distinct from the {@see Customer} (the
 * natural-person identity) (parties-core, design D2/D5; party-registry — Requirement: Account — Billing
 * Container). Co-provisioned 1:1 with its Customer in the same transaction (§ 4.7, § 7.1 step 3), born `active`
 * with account type `personal` (DEC-068 — the sole launch type).
 *
 * Persistence-only by design (D7): the {@see CreateCustomer} action is the sole writer (it provisions the
 * Account through the Customer's `account()` relation), so `$guarded = []` carries no
 * mass-assignment-from-request risk. The Account is event-silent — its creation records NO domain event (the
 * PRD § 15 catalog names none — design D7); do NOT invent an `AccountCreated`.
 *
 * The Account is explicitly NOT a monetary-balance or credit ledger (§ 4.7) — there is no "Account Credit"
 * instrument (goodwill is vouchers; Club Credits live on the Profile), so it carries no balance/credit
 * attribute; the payment-provider reference is provisioned lazily (DEC-014), not here. `default_currency` is
 * an ISO 4217 PREFERENCE string (design D9), not a money amount. This change defines no transition out of
 * `active` (design D2). The {@see customer()} link is a WITHIN-module relation (the boundary law forbids only
 * CROSS-module relations).
 *
 * @property int $id
 * @property int $customer_id
 * @property AccountType $account_type
 * @property string $name
 * @property AccountStatus $status
 * @property string $default_currency
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $table = 'parties_accounts';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The owning Customer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module
     * relation ban does not apply). The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Account::factory()->create()`.
     */
    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'status' => AccountStatus::class,
            'version' => 'integer',
        ];
    }
}
