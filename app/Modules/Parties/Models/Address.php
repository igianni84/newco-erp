<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateCustomerAddress;
use Carbon\CarbonInterface;
use Database\Factories\Parties\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Address — a Customer's billing Address (parties-anonymisation task 2.1; design D4; party-registry —
 * Requirement: Customer Address; DEC-068 / AC-K-XM-25). It belongs to EXACTLY ONE Customer via a WITHIN-module FK
 * to `parties_customers`, so the cross-module ban (invariant 10) does not apply: the {@see customer()} relation is
 * a within-module `belongsTo`, and its inverse is the {@see Customer::addresses()} `hasMany` (a Customer MAY hold
 * zero or more Addresses). At launch only BILLING Addresses are modelled; shipping Addresses + the "Address used
 * at purchase" invoice snapshot are downstream (Module C / Module S+E) and out of this change.
 *
 * The personal address fields (`line1`, `line2?`, `locality`, `region?`, `postal_code`, `country_code`) are the
 * GDPR-erasable data held here — the anonymisation action (`AnonymiseCustomer`, task 3.2) overwrites them (and the
 * optional `company_name` / `vat_id`) with deterministic placeholders in the SAME transaction as the Customer PII
 * overwrite, PRESERVING the row (never deleting it — design D1/D4). The company-billing affordance (DEC-068 /
 * AC-K-XM-25): OPTIONAL `company_name` + `vat_id` support an individual collector who transacts through their own
 * company for fiscal reasons — the Customer stays the natural person and carries NO company data and NO B2C/B2B
 * discriminator. `country_code` is an ISO 3166-1 alpha-2 code validated at the {@see CreateCustomerAddress}
 * action boundary (two uppercase letters — not a DB enum/CHECK, mirroring the ISO 4217 currency-code discipline).
 *
 * Persistence-only by design: {@see CreateCustomerAddress} is the sole writer — it assembles the attributes
 * internally, so `$guarded = []` carries no mass-assignment-from-request risk (mirrors the sibling
 * {@see ClubCredit} / spine models). An Address is a MUTABLE child overwritten in place, so it carries NO
 * `version` column (the `parties_club_credits` precedent, NOT the versioned identity spine) and records no domain
 * event (§ 15.1 names no Address event — the lone anonymisation event is `CustomerAnonymised`, task 3.4).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $line1
 * @property string|null $line2
 * @property string $locality
 * @property string|null $region
 * @property string $postal_code
 * @property string $country_code
 * @property string|null $company_name
 * @property string|null $vat_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $table = 'parties_addresses';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The owning Customer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module relation
     * ban does not apply). The reference is required (a non-nullable FK). Its inverse is the
     * {@see Customer::addresses()} `hasMany`.
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
     * static analysis infer the factory's model for `Address::factory()->create()`.
     */
    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
}
