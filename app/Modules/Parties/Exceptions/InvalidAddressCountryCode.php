<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Actions\CreateCustomerAddress;
use App\Platform\Money\Currency;
use RuntimeException;

/**
 * Raised when {@see CreateCustomerAddress} is given a `country_code` outside the ISO 3166-1 alpha-2 format
 * (parties-anonymisation task 2.1; design D4; party-registry — Requirement: Customer Address). The country code
 * is a fixed-width code like the ISO 4217 currency code (`parties_addresses.country_code` is `string(2)`),
 * validated at the ACTION boundary — two uppercase ASCII letters — rather than by a DB enum/CHECK (the migration's
 * "validated at the CreateCustomerAddress action boundary, not a DB enum/CHECK"; the fail-closed ISO-code
 * discipline of {@see Currency}). No launch country-set exists (collectors are international),
 * so the boundary validates FORMAT, not membership of a fixed set.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12). Unlike {@see DuplicateCustomerEmail}
 * — which omits the email because an email is PII — a country code is NOT personal data (a two-letter token like
 * `IT`), so the offending value IS interpolated (the `:producer` / `:club` id discipline) to make the rejection
 * self-documenting for the operator who supplied it. `(string)` coerces the translator return (typed `mixed` by
 * Larastan) to the RuntimeException message contract.
 */
class InvalidAddressCountryCode extends RuntimeException
{
    public static function forCode(string $countryCode): self
    {
        return new self((string) __('parties.address.invalid_country_code', ['country' => $countryCode]));
    }
}
