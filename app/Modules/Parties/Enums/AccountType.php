<?php

namespace App\Modules\Parties\Enums;

/**
 * The Account type classifier (design D2; party-registry — Requirement: Account —
 * Billing Container).
 *
 * At launch the only supported account type is `personal` (Module K PRD § 4.7 /
 * spec/04-decisions/decisions.md DEC-068 — one personal Account per Customer); a
 * business/organisation account type is out of the B2C-only MVP. Modelled as a
 * single-case backed enum exactly like `ProductType::Wine`: a future account type
 * is a new case here, never a reshape of the Account table. Every Account is born
 * `Personal`.
 *
 * - case name    = the type in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum AccountType: string
{
    case Personal = 'personal';
}
