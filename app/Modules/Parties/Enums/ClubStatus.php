<?php

namespace App\Modules\Parties\Enums;

/**
 * The Club lifecycle domain (design D2; party-registry — Requirement: Club / Birth
 * States Recorded, Lifecycle Transitions Deferred).
 *
 * The spec's verbatim Club state domain `active → sunset → closed` (Module K PRD
 * § 4.3). A Club is born `Active` (its steady state); `sunset` blocks new
 * memberships and new offers while preserving existing Profiles (the § 10
 * dissolution workflow); `closed` is terminal once all members have migrated or
 * expired. This change stores the state but writes NO transition and emits no
 * `ClubSunset`/`ClubClosed` (those arrive with the deferred
 * `parties-membership-lifecycle` change). The full domain is defined now so that
 * change can drive it without a migration.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ClubStatus: string
{
    case Active = 'active';
    case Sunset = 'sunset';
    case Closed = 'closed';
}
