<?php

namespace App\Modules\Parties\Enums;

/**
 * The Producer lifecycle domain (design D2; party-registry — Requirement: Producer
 * Registry / Birth States Recorded, Lifecycle Transitions Deferred).
 *
 * The spec's verbatim Producer state domain `draft → active → retired` (Module K
 * PRD § 4.4). A Producer is born `Draft`; activation requires KYC verification and
 * retirement preserves existing Product Masters while blocking new activations (the
 * § 10 offboarding cascade, BR-K-Producer-4). This change stores the state but
 * writes NO transition and emits no `ProducerActivated`/`ProducerRetired` (those
 * arrive with the deferred `parties-membership-lifecycle` change). The full domain
 * is defined now so that change can drive it without a migration.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ProducerStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}
