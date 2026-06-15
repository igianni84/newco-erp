<?php

namespace App\Modules\Catalog\Enums;

/**
 * The four-state lifecycle of every product-catalog spine entity (design D3;
 * product-catalog — Requirement: Stored Lifecycle State, Transitions Deferred).
 *
 * The spec's verbatim lifecycle domain `draft → reviewed → active → retired`
 * (Module 0 PRD § 4.1). Every spine table carries a `lifecycle_state` column
 * (string + driver-guarded Postgres CHECK + this cast) and every entity is born
 * `Draft` (`*Created` = `<null> → draft`, § 14.2).
 *
 * This change stores the state but writes NO transition: there is no review,
 * approve, activate, or retire path and no `*Activated`/`*Retired` emission — the
 * Creator→Reviewer→Approver approval needs actor identity (the open Identity/auth
 * ADR) and the Producer-activation gate needs Module K. The full four-case domain
 * is defined now so `catalog-lifecycle-approval` can drive it without a migration
 * (proposal slice boundary).
 *
 * - case name    = the state in PascalCase (Catalog vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum LifecycleState: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Active = 'active';
    case Retired = 'retired';
}
