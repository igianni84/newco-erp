<?php

namespace App\Modules\Parties\Enums;

/**
 * The immutable party-type marker carried by every Party subtype (design D1;
 * party-registry — Requirement: Party-Type Marker on Subtype).
 *
 * BR-K-Identity-5: the marker is set at creation and immutable thereafter — a
 * Customer can never become a Supplier (Module K PRD § 14.1). It is modelled
 * marker-on-subtype (ADR `2026-06-15-party-type-marker-on-subtype`): Customer and
 * Supplier are distinct `parties_*` tables, each fixing its own marker, so the
 * immutability holds *by construction* rather than by guarding a mutable
 * discriminator. There is no shared `parties_parties` registry row in this change.
 *
 * The full BR-K-Identity-5 domain — `customer`, `supplier`, `third_party_owner`
 * (spec/04-decisions/decisions.md DEC-067) — is declared here now even though only
 * `customer` and `supplier` are produced this slice: `third_party_owner` has no
 * Party entity yet (it is a Module B inventory-ownership concept, deferred to a
 * later `parties-party-registry` slice), so defining it now spares that slice an
 * enum migration. Producer is NOT a Party (§ 4.4) and carries no marker.
 *
 * - case name    = the marker in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum PartyType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case ThirdPartyOwner = 'third_party_owner';
}
