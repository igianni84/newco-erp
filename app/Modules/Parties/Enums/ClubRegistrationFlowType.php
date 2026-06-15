<?php

namespace App\Modules\Parties\Enums;

/**
 * The Club registration-flow classifier (design D2; party-registry — Requirement:
 * Club).
 *
 * The registration / approval flow that governs how new applications enter a Club
 * (Module K PRD § 4.3): `open_registration`, `application_with_approval`,
 * `invitation_only`, `link_onboarding`. Unlike the status enums this is not a
 * lifecycle state but a fixed per-Club configuration attribute set at creation; the
 * four flows are the full launch domain (the application-vs-invitation distinction
 * is the § 7 Onboarding concern). A future flow is a new case here, never a reshape
 * of the Club table.
 *
 * - case name    = the flow in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ClubRegistrationFlowType: string
{
    case OpenRegistration = 'open_registration';
    case ApplicationWithApproval = 'application_with_approval';
    case InvitationOnly = 'invitation_only';
    case LinkOnboarding = 'link_onboarding';
}
