<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Events\CustomerCreated;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Customer — NewCo's natural-person registry (B2C only; parties-core, design D1/D2/D5/D6; party-registry —
 * Requirement: Customer Identity). A Party subtype carrying the immutable party-type marker `customer`
 * (BR-K-Identity-5 holds *by construction* — Customer is a distinct table from Supplier), born `pending`
 * (§ 4.1). The record carries no B2C/B2B discriminator (DEC-068 / DEC-017).
 *
 * Persistence-only by design (D5/D7): the {@see CreateCustomer} action is the sole writer — in ONE transaction
 * it inserts the Customer (born `pending`, marker `customer`, `originating_club_id` NULL), co-provisions the
 * 1:1 {@see Account} through the {@see account()} relation, and records ONLY {@see CustomerCreated} (the Account
 * is event-silent — design D7) — so `$guarded = []` carries no mass-assignment-from-request risk.
 * `email`/`name`/`phone`/`date_of_birth` are the personal-data attributes held here (where GDPR erasure
 * operates), kept OUT of the event payload. `preferred_currency`/`preferred_locale` are ISO 4217 / locale
 * PREFERENCE strings (design D9), not money. This change defines no transition out of `pending` (design D2).
 *
 * The {@see originatingClub()} link is the BR-K-OC-2 seam (design D6): a nullable within-module FK created
 * `NULL` with NO mutation surface in this change (the one-shot lock fires on the first membership approval —
 * deferred). Both relations are WITHIN-module (the boundary law forbids only CROSS-module relations).
 *
 * @property int $id
 * @property string $email
 * @property string $name
 * @property string|null $phone
 * @property CarbonImmutable|null $date_of_birth
 * @property PartyType $party_type
 * @property string $preferred_currency
 * @property string $preferred_locale
 * @property CustomerStatus $status
 * @property int|null $originating_club_id
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Account|null $account
 * @property-read Club|null $originatingClub
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $table = 'parties_customers';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The co-provisioned 1:1 billing Account — a WITHIN-module `hasOne` (both entities are Module K, so the
     * cross-module relation ban does not apply). Created in the same transaction as the Customer (design D5);
     * `account_type`/`status` live on {@see Account}.
     *
     * @return HasOne<Account, $this>
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'customer_id');
    }

    /**
     * The Originating Club — a WITHIN-module `belongsTo`. OPTIONAL (a nullable FK): born `NULL` and never set
     * by this change (design D6, BR-K-OC-2 — the one-shot lock arrives with the deferred membership-approval
     * change). Nullability lives in the column / the `@property Club|null` annotation.
     *
     * @return BelongsTo<Club, $this>
     */
    public function originatingClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'originating_club_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Customer::factory()->create()`.
     */
    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'party_type' => PartyType::class,
            'status' => CustomerStatus::class,
            'version' => 'integer',
        ];
    }
}
