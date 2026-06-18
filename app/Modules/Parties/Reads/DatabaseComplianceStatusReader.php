<?php

namespace App\Modules\Parties\Reads;

use App\Modules\Parties\Contracts\ComplianceStatus;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use Illuminate\Database\Eloquent\Builder;

/**
 * The database-backed {@see PartyComplianceStatusReader} — it reads the `(sanctions_status, active-Hold-list)`
 * tuple straight from `parties_customers` / `parties_profiles` / `parties_holds` (parties-holds, design L6;
 * party-registry — Requirement: Hold and Sanctions Read-API). Bound to the interface in PartiesServiceProvider
 * and stateless (no injected dependencies) — a plain `bind` (fresh per resolve) is enough.
 *
 * Cascade resolves at READ, never by writing duplicate Hold rows (design L6): {@see forProfile()} unions the
 * Profile's own active Holds with its parent Customer's active Holds in ONE query (the Customer→Profile cascade,
 * BR-K-Hold-3) and reads the sanctions status off the parent Customer; a Profile-scope Hold is keyed by
 * `(profile, that id)` so it isolates — a sibling Profile's read never matches it (BR-K-Hold-4), and the
 * Customer scope does not see it either. {@see forCustomer()} reads only Customer-scope active Holds.
 * Account-scope Holds are intentionally not cascaded into either read (the PRD specifies no Account cascade —
 * design L6 risk note).
 *
 * It returns the PII-free {@see ComplianceStatus} DTO carrying {@see HoldType}s, never the `Hold` model (the
 * no-model-leak boundary law) — this is the contract downstream transaction-initiation surfaces (Module S/C/E)
 * consume by-position; the blocking is theirs (Module K is Hold-blind, DEC-181).
 */
class DatabaseComplianceStatusReader implements PartyComplianceStatusReader
{
    public function forCustomer(int $customerId): ComplianceStatus
    {
        $customer = Customer::query()->findOrFail($customerId);

        return new ComplianceStatus(
            sanctionsStatus: $customer->sanctions_status,
            activeHoldTypes: $this->activeHoldTypesForScopes([
                [HoldScope::Customer, $customerId],
            ]),
        );
    }

    public function forProfile(int $profileId): ComplianceStatus
    {
        $profile = Profile::query()->findOrFail($profileId);
        $customer = Customer::query()->findOrFail($profile->customer_id);

        // Cascade at READ (BR-K-Hold-3): a Profile sees its OWN active Holds UNION its parent Customer's active
        // Holds; the sanctions status is the parent Customer's. A Profile-scope Hold isolates (BR-K-Hold-4) — it
        // is keyed by (profile, this id), so neither a sibling Profile nor the Customer scope matches it.
        return new ComplianceStatus(
            sanctionsStatus: $customer->sanctions_status,
            activeHoldTypes: $this->activeHoldTypesForScopes([
                [HoldScope::Profile, $profileId],
                [HoldScope::Customer, $profile->customer_id],
            ]),
        );
    }

    /**
     * The DISTINCT active Hold types across the given scopes — the cascade union resolved in ONE query. Each
     * scope is a (`scope_type`, `scope_id`) pair; the result is the set of types carrying at least one `active`
     * Hold on any of them. Distinct because "is this scope clear?" is answered by WHICH restrictions apply, not
     * how many rows exist (a scope may carry multiple concurrent Holds of one type — BR-K-Hold-1).
     *
     * @param  non-empty-list<array{0: HoldScope, 1: int}>  $scopes
     * @return list<HoldType>
     */
    private function activeHoldTypesForScopes(array $scopes): array
    {
        $types = Hold::query()
            ->where('status', HoldStatus::Active->value)
            ->where(function (Builder $query) use ($scopes): void {
                // status = active AND ( (scope_type, scope_id) matches ANY of the cascade scopes ).
                foreach ($scopes as [$scopeType, $scopeId]) {
                    $query->orWhere(function (Builder $inner) use ($scopeType, $scopeId): void {
                        $inner->where('scope_type', $scopeType->value)
                            ->where('scope_id', $scopeId);
                    });
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Hold $hold): HoldType => $hold->hold_type)
            ->unique(fn (HoldType $type): string => $type->value);

        return array_values($types->all());
    }
}
