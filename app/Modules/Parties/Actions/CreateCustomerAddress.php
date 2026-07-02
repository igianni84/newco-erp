<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Exceptions\InvalidAddressCountryCode;
use App\Modules\Parties\Models\Address;
use App\Platform\Money\Currency;

/**
 * Creates a billing {@see Address} scoped to a Customer (parties-anonymisation task 2.1; design D4;
 * party-registry — Requirement: Customer Address; DEC-068 / AC-K-XM-25).
 *
 * A THIN creation action: unlike the sibling spine Create* actions it records NO domain event (§ 15.1 names no
 * Address event — the change's lone new event is `CustomerAnonymised`, task 3.4) and needs no transaction (a
 * single Eloquent insert is atomic). It is named `Create*` deliberately so the exhaustive non-`Create*` Action
 * allow-list (`SupplyLifecycleChainTest`) filters it out — an Address creation is not a lifecycle transition.
 *
 * ONE boundary guard enforces the launch invariant the schema deliberately does not (the migration's "validated at
 * the CreateCustomerAddress action boundary, NOT a DB enum/CHECK"):
 *   - ISO 3166-1 ALPHA-2 COUNTRY CODE: `country_code` must be exactly two uppercase ASCII letters — the fail-closed
 *     ISO-code discipline of {@see Currency}, but validating FORMAT rather than membership of a
 *     fixed set (no launch country-set exists — collectors are international). A non-conforming code is rejected with
 *     a localized {@see InvalidAddressCountryCode} ahead of persistence.
 *
 * The `customer_id` within-module FK is the structural backstop for a non-existent Customer reference (the
 * {@see CreateProfile} pattern — a bad reference fails the insert). The company-billing fields (`company_name` /
 * `vat_id`, DEC-068) are OPTIONAL — an individual collector who transacts through their own company; the Customer
 * stays the natural person and carries no company data. The Address's personal fields (and any company fields) are
 * overwritten with deterministic placeholders on anonymisation (`AnonymiseCustomer`, task 3.2), preserving the row.
 */
class CreateCustomerAddress
{
    public function handle(
        int $customerId,
        string $line1,
        string $locality,
        string $postalCode,
        string $countryCode,
        ?string $line2 = null,
        ?string $region = null,
        ?string $companyName = null,
        ?string $vatId = null,
    ): Address {
        // ISO 3166-1 alpha-2 boundary guard: exactly two uppercase ASCII letters (fail-closed — a lowercase or
        // wrong-length code is rejected, never silently normalized). The DB column is `string(2)` with no CHECK,
        // so this action is the sole format authority (design D4; the ISO 4217 currency-code discipline).
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw InvalidAddressCountryCode::forCode($countryCode);
        }

        return Address::create([
            'customer_id' => $customerId,
            'line1' => $line1,
            'line2' => $line2,
            'locality' => $locality,
            'region' => $region,
            'postal_code' => $postalCode,
            'country_code' => $countryCode,
            'company_name' => $companyName,
            'vat_id' => $vatId,
        ]);
    }
}
