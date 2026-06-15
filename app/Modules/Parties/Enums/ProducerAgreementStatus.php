<?php

namespace App\Modules\Parties\Enums;

/**
 * The ProducerAgreement lifecycle domain (design D2; party-registry — Requirement:
 * ProducerAgreement / Birth States Recorded, Lifecycle Transitions Deferred).
 *
 * The spec's verbatim ProducerAgreement state domain
 * `draft → active → superseded | terminated` (Module K PRD § 4.6.1). An agreement
 * is born `Draft`; `superseded` marks one replaced by a newer agreement
 * (renewal/amendment — the two are paired in audit history, BR-K-Agreement-3);
 * `terminated` ends it. The "at most one active agreement per Producer scope" rule
 * is an activation-time invariant, out of this creation-only slice. This change
 * stores the state but writes NO transition and emits no
 * `ProducerAgreementSuperseded` (deferred to `parties-membership-lifecycle`). The
 * full domain is defined now so that change can drive it without a migration.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ProducerAgreementStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
    case Terminated = 'terminated';
}
