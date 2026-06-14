<?php

namespace App\Modules\Catalog\Enums;

/**
 * The category-neutral product classifier on a Product Master (design D2;
 * product-catalog — Requirement: Category-Neutral Product Type).
 *
 * `Product Type` is the §16 Wine→Product generalisation made first-class: the
 * switch that selects, per product, the applicable per-type attribute set, the
 * variant-defining dimension, the type-defined identity-uniqueness key, and (when
 * it lands) the enrichment adapter. At launch the only supported value is `Wine`
 * (AC-0-XM-9 / MVP-DEC-004 / DEC-065) — a Master of any other type is rejected
 * fail-closed by the creation Action, never silently accepted.
 *
 * Modelled as a backed enum, not a reference table: §16 forbids a dynamic EAV /
 * rules engine, so a single-row table would over-model. Adding a future Product
 * Type is a new case here plus its per-type attribute table(s) — never a reshape
 * of the neutral core (the additive-readiness §16 guardrail). Mirrors the house
 * enum style (`Module`, `Currency`, `SupportedLocale`); stored as a string column
 * on the Master with a driver-guarded Postgres CHECK + the enum cast (the
 * `domain_events.actor_role` pattern), added with the Master table.
 *
 * - case name    = the type in PascalCase (Catalog vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ProductType: string
{
    case Wine = 'wine';
}
