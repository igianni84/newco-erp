<?php

namespace App\Modules\Parties\Support;

/**
 * AnonymisedPlaceholders — the deterministic, per-Customer-unique placeholder set the GDPR right-to-erasure
 * overwrite writes over a Customer's PII and its Addresses' personal fields (parties-anonymisation task 3.1;
 * design D1; party-registry — Requirement: Customer Anonymisation; AC-K-J-9 / FSM-16 / BR-Customer-2). The
 * `AnonymiseCustomer` action (task 3.2) reads it inside one transaction to overwrite the Customer and every
 * scoped Address in place, preserving the rows.
 *
 * DETERMINISTIC, NEVER random/faker (design D1): every value is derived purely from the Customer id, so the id
 * keying earns two things at once —
 *   (1) it PRESERVES the UNIQUE email invariant (`parties_customers.email` is globally unique — BR-K-Identity-1):
 *       `anonymised+{id}@anonymised.invalid` is distinct per Customer, so anonymising two Customers never collides
 *       on the unique index (a random email could — the rejected alternative in design D1); and
 *   (2) it leaves a stable, PII-free breadcrumb (`Anonymised Customer {id}`) an operator can still refer to by id.
 * The `.invalid` TLD is RFC 6761-reserved and guaranteed non-routable, so a placeholder email can never reach a
 * real inbox. Reproducibility also makes the overwrite testable and the whole action idempotent.
 *
 * Two projections centralise the field => placeholder mapping the action consumes, so the "which columns, what
 * value" knowledge lives in ONE place:
 *   - {@see customerAttributes()} — the four Customer PII columns (`email`/`name` id-derived; the two nullable
 *     columns `phone`/`date_of_birth` erased to NULL);
 *   - {@see addressAttributes()} — the eight `parties_addresses` personal/company fields. These are CONSTANT (not
 *     id-keyed): `parties_addresses` carries no unique constraint, so a shared sentinel both erases the data and
 *     leaks no id-linkable value. The nullable fields (`line2`/`region`/`company_name`/`vat_id`) are nulled; the
 *     NOT-NULL free-text fields (`line1`/`locality`/`postal_code`) take {@see REDACTED}; and `country_code` takes
 *     {@see REDACTED_COUNTRY_CODE} — the ISO 3166-1 alpha-2 "unknown" code, deliberately TWO characters so it fits
 *     the `string(2)` column on Postgres (a longer sentinel would pass SQLite yet break PG — the cross-engine
 *     trap) while still satisfying the CreateCustomerAddress `/^[A-Z]{2}$/` format law.
 *
 * A pure value object — no I/O, no event, no model dependency (it only DERIVES the overwrite map from an int id).
 * It lives under Support/ as an internal within-module helper: deliberately NOT Contracts/ (that folder is Module
 * K's cross-module public surface — this is internal to the anonymisation flow) and NOT Actions/ (which the
 * SupplyLifecycleChainTest lifecycle-Action glob governs).
 */
class AnonymisedPlaceholders
{
    /**
     * The erased sentinel for the NOT-NULL free-text Address fields (`line1` / `locality` / `postal_code`). A
     * shared constant — the Address placeholders are not id-keyed (no unique constraint to preserve).
     */
    private const REDACTED = 'Anonymised';

    /**
     * The erased `country_code` — `ZZ`, the ISO 3166-1 alpha-2 user-assigned code conventionally meaning
     * "unknown". EXACTLY two uppercase letters, so it fits the `parties_addresses.country_code` `string(2)`
     * column on Postgres AND passes the CreateCustomerAddress `/^[A-Z]{2}$/` format guard.
     */
    private const REDACTED_COUNTRY_CODE = 'ZZ';

    private function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {}

    /**
     * Derive the placeholder set for a Customer id — the sole construction path (deterministic; design D1). The
     * same id always yields the same placeholders; two distinct ids yield distinct emails (UNIQUE-safe).
     */
    public static function for(int $customerId): self
    {
        return new self(
            email: "anonymised+{$customerId}@anonymised.invalid",
            name: "Anonymised Customer {$customerId}",
        );
    }

    /**
     * The Customer PII overwrite map (DB column => placeholder) the anonymisation action writes over the four
     * personal-data columns: `email`/`name` id-derived (above); `phone`/`date_of_birth` erased to NULL (both are
     * nullable columns).
     *
     * @return array{email: string, name: string, phone: null, date_of_birth: null}
     */
    public function customerAttributes(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'phone' => null,
            'date_of_birth' => null,
        ];
    }

    /**
     * The Address personal-field overwrite map (DB column => placeholder) the action writes over EVERY scoped
     * Address in the same transaction (design D1/D4). Constant, not id-keyed — `parties_addresses` has no unique
     * constraint, so a shared sentinel erases the data with no re-identification vector.
     *
     * @return array{line1: string, line2: null, locality: string, region: null, postal_code: string, country_code: string, company_name: null, vat_id: null}
     */
    public function addressAttributes(): array
    {
        return [
            'line1' => self::REDACTED,
            'line2' => null,
            'locality' => self::REDACTED,
            'region' => null,
            'postal_code' => self::REDACTED,
            'country_code' => self::REDACTED_COUNTRY_CODE,
            'company_name' => null,
            'vat_id' => null,
        ];
    }
}
