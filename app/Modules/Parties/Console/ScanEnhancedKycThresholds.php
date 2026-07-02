<?php

namespace App\Modules\Parties\Console;

use App\Modules\Parties\Actions\EvaluateEnhancedKycThreshold;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Reads\NullCustomerTransactionTotalsReader;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The periodic enhanced-KYC AML-threshold scan (change parties-enhanced-kyc-threshold, task 5.1; design D7;
 * party-registry — Requirement: Enhanced-KYC Threshold Detection). It iterates every Customer and invokes the
 * detection orchestrator {@see EvaluateEnhancedKycThreshold} — the SAME unit the future at-order-completion trigger
 * (Module S, deferred) will call, so "both paths, identical state" (AC-K-J-7a) holds BY CONSTRUCTION.
 *
 * Runs INLINE on the scheduler tick — NOT a queued/async workflow (design D7;
 * decisions/2026-06-12-event-substrate-and-audit-store.md classifies a scheduler tick as not-queued), so it does NOT
 * trip the still-open queue-driver ADR gate. With the launch-bound {@see NullCustomerTransactionTotalsReader}
 * every totals read is zero, so the scan escalates NOTHING until Module S ships the real adapter (design D4) — a
 * correct no-op that costs one locked re-read per Customer.
 *
 * Idempotency lives in the orchestrator, not here: it latches on `enhanced_kyc_flag` under a locked re-read, so a
 * daily re-scan of a still-above-threshold Customer changes nothing. This command therefore iterates ALL Customers
 * unconditionally and lets the orchestrator decide — no flag pre-filter to keep the two paths in step (design D1).
 *
 * The actor resolves to {@see ActorRole::System} on the console/scheduler tick: no operator is
 * authenticated, so the {@see ActorContext} natural resolution supplies System — no explicit
 * `runAs()` override needed (nor used anywhere in the app; console == unauthenticated == System by design).
 *
 * Registered explicitly via withCommands() in bootstrap/app.php (auto-discovery scans only app/Console/Commands) and
 * scheduled ->daily() in routes/console.php — the events:sweep precedent.
 */
class ScanEnhancedKycThresholds extends Command
{
    protected $signature = 'parties:scan-enhanced-kyc-thresholds';

    protected $description = 'Scan every customer for an enhanced-KYC AML-threshold crossing (€10k single / €50k rolling-12mo) and escalate on a first breach.';

    public function handle(EvaluateEnhancedKycThreshold $evaluate): int
    {
        $scanned = 0;

        // lazyById() streams Customers one at a time, paginated by ASCENDING id — immune to the offset drift a plain
        // chunk() would suffer as the orchestrator mutates `enhanced_kyc_flag` mid-scan (the id cursor is stable).
        foreach (Customer::query()->lazyById() as $customer) {
            $evaluate->handle($customer->id);
            $scanned++;
        }

        // The run summary — the observability floor that a scheduled compliance scan actually fired, and its coverage.
        // Each escalation is separately audited via the review row + the CustomerEnhancedKycReviewRequired event.
        Log::info(sprintf('parties:scan-enhanced-kyc-thresholds complete: scanned=%d', $scanned), [
            'scanned' => $scanned,
        ]);

        return self::SUCCESS;
    }
}
