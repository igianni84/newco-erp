<?php

namespace App\Modules\Parties\Enums;

/**
 * The Hold lifecycle-status domain (design L1; party-registry — Requirement: Hold
 * Registry, Hold Lifecycle and Lift Discipline).
 *
 * The spec's verbatim two-state Hold lifecycle `active | lifted` (Module K PRD
 * § 4.8 — a Hold is born `active` and moves to `lifted` once cleared). A Hold has no
 * other terminal state at launch; expiry (a possible third state) is an
 * under-specified, deferred automation seam (proposal slice boundary).
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum HoldStatus: string
{
    case Active = 'active';
    case Lifted = 'lifted';
}
