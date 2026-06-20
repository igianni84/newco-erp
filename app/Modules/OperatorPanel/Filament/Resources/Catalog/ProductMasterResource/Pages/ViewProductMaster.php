<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/**
 * The read-only Product Master view, plus the review-and-approval AND retire/reopen lifecycle ACTIONS that
 * operate on the viewed Master (operator-console-catalog-master, tasks 2.1/4.1/5.1; design L1/L2/L5/L7/L8;
 * ADR 2026-06-19; spec — Operator advances a Product Master through the review-and-approval lifecycle, and
 * retires/reopens it).
 *
 * There is still NO field-edit here — the Catalog backend ships no update Action, so the console offers
 * lifecycle TRANSITIONS, never in-place edits (design L2; proposal slice-boundary). Each header action is a
 * write-through {@see Action} that routes to a Catalog domain action and NEVER writes `lifecycle_state`
 * itself (the no-Eloquent-write rule, task 1.2):
 *   - "Submit for review" → {@see SubmitProductMasterForReview} (`draft → reviewed`, audit-only);
 *   - "Reject" → {@see RejectProductMasterReview} (collects `notes`; stays `reviewed`, audit-only);
 *   - "Activate" → {@see ActivateProductMaster} (`reviewed → active`, records ProductMasterActivated). It
 *     carries a confirmation modal whose description is the localized "second actor required" affordance — the
 *     console SURFACES the domain's Creator → Reviewer → Approver separation-of-duties floor (a distinct
 *     approver) and the Producer activation gate, it never re-checks either (design L5/L6);
 *   - "Retire" → {@see RetireProductMaster} (`active → retired`, single-entity — it PRESERVES existing active
 *     children; records ProductMasterRetired);
 *   - "Reopen" → {@see ReopenProductMaster} (`retired → reviewed`, audit-only — the next activation re-checks
 *     the Producer gate, design L7).
 * The operator-driven cascade retire lands in task 5.2 as a further header action here.
 *
 * The console SURFACES the domain's decision, it never reimplements the floor (design L5): the from-state
 * guard, the Creator → Reviewer → Approver separation-of-duties and the producer gate all live in the
 * domain. {@see surfaceLifecycleOutcome()} runs the action and renders the outcome — a success notification
 * on completion, a danger notification carrying the action's already-localized message when the domain
 * rejects the transition. The rejection is caught by its base type ({@see RuntimeException} — every Catalog
 * lifecycle rejection extends it) rather than `use Catalog\Exceptions\…`, keeping the console's cross-module
 * surface exactly {Models, Actions} (the import-boundary carve-out, task 1.3). All copy is localized
 * (invariant 12).
 */
class ViewProductMaster extends ViewRecord
{
    protected static string $resource = ProductMasterResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label((string) __('operator_console.product_master.actions.submit'))
                ->action(function (ProductMaster $record): void {
                    self::surfaceLifecycleOutcome(
                        fn () => app(SubmitProductMasterForReview::class)->handle($record),
                        (string) __('operator_console.product_master.notifications.submitted'),
                    );
                }),
            Action::make('reject')
                ->label((string) __('operator_console.product_master.actions.reject'))
                ->form([
                    Textarea::make('notes')
                        ->label((string) __('operator_console.product_master.fields.rejection_notes'))
                        ->required(),
                ])
                ->action(
                    /** @param  array<string, mixed>  $data */
                    function (ProductMaster $record, array $data): void {
                        // The form's `required` Textarea makes `notes` a present string on the happy path;
                        // narrow the array<string, mixed> state to the action's typed contract at the boundary.
                        $notes = is_string($data['notes'] ?? null) ? $data['notes'] : '';

                        self::surfaceLifecycleOutcome(
                            fn () => app(RejectProductMasterReview::class)->handle($record, $notes),
                            (string) __('operator_console.product_master.notifications.rejected'),
                        );
                    }
                ),
            Action::make('activate')
                ->label((string) __('operator_console.product_master.actions.activate'))
                // The "second actor required" affordance (design L5/L6): a confirmation step whose description
                // tells the operator BEFORE they commit that a distinct approver is required. The domain
                // (approval governance + the Producer activation gate) is the sole authority — a same-actor or
                // gate-blocked activation is rejected there and surfaced below, never pre-checked here.
                ->requiresConfirmation()
                ->modalDescription((string) __('operator_console.product_master.affordance.second_actor'))
                ->action(function (ProductMaster $record): void {
                    self::surfaceLifecycleOutcome(
                        fn () => app(ActivateProductMaster::class)->handle($record),
                        (string) __('operator_console.product_master.notifications.activated'),
                    );
                }),
            // Single-entity retire (`active → retired`): a hierarchy parent carries no reference-integrity
            // guard, so retiring a Master PRESERVES its existing active children — only NEW activation under
            // the now-retired Master is blocked (design L7; § 4.5). The operator-driven cascade is a distinct
            // action (task 5.2). Retire carries only the operator-principal floor (no distinct-actor SoD —
            // that is the activation step's floor), so the domain rejects only an out-of-state retire here.
            Action::make('retire')
                ->label((string) __('operator_console.product_master.actions.retire'))
                ->action(function (ProductMaster $record): void {
                    self::surfaceLifecycleOutcome(
                        fn () => app(RetireProductMaster::class)->handle($record),
                        (string) __('operator_console.product_master.notifications.retired'),
                    );
                }),
            // Reopen (`retired → reviewed`): audit-only, no domain event — it returns the Master to the
            // activatable `reviewed` state, where the next Activate re-runs the Producer gate (design L7).
            Action::make('reopen')
                ->label((string) __('operator_console.product_master.actions.reopen'))
                ->action(function (ProductMaster $record): void {
                    self::surfaceLifecycleOutcome(
                        fn () => app(ReopenProductMaster::class)->handle($record),
                        (string) __('operator_console.product_master.notifications.reopened'),
                    );
                }),
        ];
    }

    /**
     * Run a Catalog lifecycle action and surface its outcome to the operator. On completion: a success
     * notification. When the domain REJECTS the transition — an out-of-state `IllegalLifecycleTransition`, an
     * approval-governance or producer-gate violation (all extend {@see RuntimeException}) — a danger
     * notification carrying the action's already-localized message, leaving the Master unchanged (the
     * rejecting action's transaction rolled back). The console never re-checks the from-state, the SoD floor
     * or the producer gate itself (design L5); it catches the rejection by base type so it imports nothing
     * from `Catalog\Exceptions` (the {Models, Actions} surface, task 1.3) — and the docblock names the
     * concrete exception in prose, NOT as a `{@see}` type, so Pint's fully_qualified_strict_types cannot
     * re-add the forbidden import (lessons.md).
     *
     * @param  Closure(): mixed  $run  invokes the Catalog domain action (its return value is unused)
     */
    private static function surfaceLifecycleOutcome(Closure $run, string $successTitle): void
    {
        try {
            $run();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title((string) __('operator_console.product_master.notifications.action_failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($successTitle)
            ->send();
    }
}
