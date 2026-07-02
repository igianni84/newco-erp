<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Models\Address;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;

/**
 * Assembles a Customer's GDPR right-of-access / data-portability export (parties-anonymisation task 5.1; design
 * D5; party-registry — Requirement: Customer Data Export; canon J-9b; PRD § 12 — "operationally executed … not
 * modelled as a state machine").
 *
 * MINIMAL, SYNCHRONOUS, IN-MEMORY (design D5): the Action assembles a plain structured array and RETURNS it to the
 * operator. It is strictly READ-ONLY — it persists NO file, records NO domain event, performs NO mutation, and
 * needs NO transaction (a pure read has nothing to make atomic). This satisfies the compliance narrative without
 * inventing an async/document pipeline and, crucially, WITHOUT tripping the still-undecided object-storage-for-
 * documents ADR (gate = INV1 issuance — root CLAUDE.md open stack decisions). Contrast {@see AnonymiseCustomer},
 * the mutating sibling (transaction + overwrite + event); this one only reads.
 *
 * The payload has two parts (the § 12 shape):
 *   - `customer` — the Customer's own personal data: the four PII columns ({@see Customer} `name`/`email`/`phone`/
 *     `date_of_birth`) plus the opaque id. `addresses` carries the personal fields of every scoped {@see Address}
 *     (also the Customer's personal data — the SAME field set the erasure overwrites, so the access-export and the
 *     right-to-erasure agree on what "the Customer's personal data" is).
 *   - `transactional_history` — a manifest of REFERENCES BY ID to the retained history. The Customer's Profiles
 *     today; the downstream Order / Voucher / Invoice id-lists join under this same key as those modules land. They
 *     are absent now — and unreachable regardless: they live in other modules, and the module-boundary law
 *     (invariant 10) forbids Module K reading their tables directly (a future change surfaces them via a read
 *     contract), so Profiles is the whole within-module manifest at launch.
 *
 * ANONYMISATION-AWARE by construction (design D5; scenario "Export of an anonymised Customer returns placeholder
 * PII"): it reads the CURRENT row state, and anonymisation is an overwrite-in-place — so for an already-anonymised
 * Customer the payload reflects the deterministic PLACEHOLDER PII ({@see AnonymisedPlaceholders} —
 * `anonymised+{id}@anonymised.invalid`, `Anonymised Customer {id}`, the `ZZ`/`Anonymised` Address sentinels), not
 * the original data. No special-casing: "current state" IS the anonymised state.
 *
 * Named `Export*` (a non-`Create*` Action): it records no event and does no transaction, but the exhaustive
 * `SupplyLifecycleChainTest` glob catches EVERY non-`Create*` Action under `Actions/`, so it is registered in that
 * test's `$anonymisationWriters` group alongside {@see AnonymiseCustomer} (task 3.4's note). A missing Customer id
 * fails the `firstOrFail()` (a `ModelNotFoundException`, the {@see AnonymiseCustomer} precedent — not a domain
 * rejection, so no localized exception).
 */
class ExportCustomerData
{
    /**
     * @return array{
     *     customer: array{id: int, name: string, email: string, phone: string|null, date_of_birth: string|null},
     *     addresses: list<array{id: int, line1: string, line2: string|null, locality: string, region: string|null, postal_code: string, country_code: string, company_name: string|null, vat_id: string|null}>,
     *     transactional_history: array{profiles: list<int>},
     * }
     */
    public function handle(int $customerId): array
    {
        $customer = Customer::query()->whereKey($customerId)->firstOrFail();

        // The Customer's Address personal data (the same fields the erasure overwrites) — read in a stable id order.
        $addresses = [];
        foreach ($customer->addresses()->orderBy('id')->get() as $address) {
            $addresses[] = [
                'id' => $address->id,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'locality' => $address->locality,
                'region' => $address->region,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
                'company_name' => $address->company_name,
                'vat_id' => $address->vat_id,
            ];
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'date_of_birth' => $customer->date_of_birth?->toDateString(),
            ],
            'addresses' => $addresses,
            // The by-id manifest of retained transactional history — Profiles now (within-module); Order/Voucher/
            // Invoice id-lists join here as those modules land (via a read contract — never a cross-module read).
            'transactional_history' => [
                'profiles' => array_values(
                    $customer->profiles()->orderBy('id')->get()
                        ->map(fn (Profile $profile): int => $profile->id)
                        ->all()
                ),
            ],
        ];
    }
}
