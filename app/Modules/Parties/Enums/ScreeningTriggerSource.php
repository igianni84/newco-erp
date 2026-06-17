<?php

namespace App\Modules\Parties\Enums;

/**
 * The sanctions-screening trigger-source domain (design L4; party-registry —
 * Requirement: Customer Sanctions Screening Lifecycle).
 *
 * Why a screening ran, recorded on the Customer's `screening_trigger_source` column
 * (additive nullable — DEC-071) at each verdict. The four sources are the § 9.2
 * trigger paths (DEC-030 / DEC-035): `onboarding` (the first screen at onboarding),
 * `cadence` (the 12-month re-screen daily job), `aml_threshold` (the €10k-single /
 * €50k-cumulative AML detection scan), and `compliance_ad_hoc` (the operator
 * case-by-case re-screen via Admin Panel). `onboarding` denotes the Customer's first
 * screening; every other source denotes a re-screen — driving the onboarding-vs-
 * rescreening event family (§ 15.6). The cadence + AML automation is deferred
 * (manual-first, § 9.5); the field and the ad-hoc path ship now.
 *
 * - case name    = the source in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ScreeningTriggerSource: string
{
    case Onboarding = 'onboarding';
    case Cadence = 'cadence';
    case AmlThreshold = 'aml_threshold';
    case ComplianceAdHoc = 'compliance_ad_hoc';
}
