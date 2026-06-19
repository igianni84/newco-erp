<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\ReactivateCustomer;
use App\Modules\Parties\Actions\SuspendCustomer;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerCreated;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\CustomerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * The compliance-screening columns (`kyc_status` + `kyc_required` + the enhanced-KYC trigger fields;
 * `sanctions_status` + `last_screening_at` / `next_rescreen_at` / `screening_trigger_source`) are added
 * additively as nullable (parties-compliance task 1.2, DEC-071): a NULL `kyc_status` is un-screened, and a NULL
 * `sanctions_status` is treated downstream as not-`passed`/blocked. The KYC and sanctions FSMs are each separate
 * from the Customer status FSM and independent of each other (§ 9.1/§ 9.2/§ 9.4); the compliance transition
 * Actions are their sole writers — the model stays persistence-only.
 *
 * The onboarding-acceptance columns (`email_verified_at` + `tc_accepted_at` + `privacy_accepted_at`) are added
 * additively as nullable (parties-membership-activation task 1.1, DEC-071/DEC-073): they are the gate inputs the
 * `ActivateCustomer` composite gate reads (§ 4.1 — `pending → active` requires email verified ∧ T&C ∧ privacy
 * accepted, alongside sanctions = passed and KYC cleared). Born `NULL` and written by the deferred consumer
 * registration surface or an operator (no setter in this slice — the additive-seam pattern); a NULL timestamp is
 * an unmet gate. `:state`/acceptance values are never carried into a domain-event payload.
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
 * @property KycStatus|null $kyc_status
 * @property bool|null $kyc_required
 * @property bool|null $enhanced_kyc_flag
 * @property CarbonImmutable|null $enhanced_kyc_at
 * @property SanctionsStatus|null $sanctions_status
 * @property CarbonImmutable|null $last_screening_at
 * @property CarbonImmutable|null $next_rescreen_at
 * @property ScreeningTriggerSource|null $screening_trigger_source
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $tc_accepted_at
 * @property CarbonImmutable|null $privacy_accepted_at
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Account|null $account
 * @property-read Club|null $originatingClub
 * @property-read Collection<int, Profile> $profiles
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
     * The Club memberships this Customer holds — a WITHIN-module `hasMany` (both entities are Module K, so the
     * cross-module relation ban does not apply). It is the cascade target of {@see SuspendCustomer}
     * / {@see ReactivateCustomer} (§ 15.1 *"Cascades to all the Customer's Profiles"* — the
     * `RetireProducer → Producer::clubs()` cascade precedent): a Customer may hold many Profiles, at most one per Club
     * (BR-K-Identity-2). The Action re-reads this set `->lockForUpdate()` inside its transaction; the relation itself
     * adds no writer (the model stays persistence-only).
     *
     * @return HasMany<Profile, $this>
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'customer_id');
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
            // compliance-screening lifecycles (parties-compliance task 1.2; design L1) — additive nullable.
            'kyc_status' => KycStatus::class,
            'kyc_required' => 'boolean',
            'enhanced_kyc_flag' => 'boolean',
            'enhanced_kyc_at' => 'immutable_datetime',
            'sanctions_status' => SanctionsStatus::class,
            'last_screening_at' => 'immutable_datetime',
            'next_rescreen_at' => 'immutable_datetime',
            'screening_trigger_source' => ScreeningTriggerSource::class,
            // onboarding-acceptance gate inputs (parties-membership-activation task 1.1; design L1) — additive
            // nullable timestamps the ActivateCustomer composite gate reads (§ 4.1); set by the deferred
            // registration surface or an operator.
            'email_verified_at' => 'immutable_datetime',
            'tc_accepted_at' => 'immutable_datetime',
            'privacy_accepted_at' => 'immutable_datetime',
        ];
    }
}
