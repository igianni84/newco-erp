<?php

namespace App\Modules\OperatorPanel\Filament\Console\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * SurfacesDomainActions — the operator-console "action wrapper", promoted out of the bespoke Product Master
 * view page into one shared concern (operator-console-catalog-spine, task 1.1; ADR 2026-06-20; design L1/L4;
 * it resolves the predecessor's design L9 deferral).
 *
 * The operator console's one law (ADR 2026-06-19): it READS module models and WRITES only through module
 * domain actions — never `$model->save()`. This concern owns the write-side discipline every console view
 * page repeats:
 *   - {@see surfaceLifecycleOutcome()} runs a domain action and renders its outcome — a success notification
 *     on completion, a danger notification carrying the action's already-localized message when the domain
 *     REJECTS the transition. Every Catalog lifecycle rejection (illegal-from-state, approval-governance,
 *     producer / activation-cascade gate, retire reference-integrity) extends RuntimeException, so it is
 *     caught by base type — nothing is imported from a module's `Exceptions` namespace, keeping the console's
 *     cross-module surface exactly {Models, Actions} (the import-boundary carve-out, task 1.3). The console
 *     never re-checks the from-state or any gate; it SURFACES the domain's decision (design L4).
 *   - {@see lifecycleAction()} builds one uniform lifecycle {@see Action} from a verb, a success-notification
 *     key and the per-entity domain-action invocation, with an optional reject-notes form and an optional
 *     confirmation affordance. The five lifecycle actions share this shape across all seven catalog consoles.
 *   - {@see recordOf()} narrows the Filament page record to the concrete model the typed domain action
 *     consumes — the one place a `Model` becomes a `ProductMaster`/`Format`/… so each invocation stays a
 *     clean, fully-typed one-liner.
 *
 * A verb whose single click has TWO lawful successful outcomes cannot name them with one fixed title, and the
 * console must never report one outcome as the other (parties-hero-package, design D11: an approve that the
 * Hero-Package capacity gate diverts lands the Profile on the waiting list, not in the membership). So the
 * success-notification key may instead be a RESOLVER — a Closure handed the value the domain action returned,
 * naming the copy for THAT outcome. Deriving is not gating: the domain has already decided and written, and the
 * resolver only READS what came back. The console still evaluates no rule of its own, still catches the
 * rejection by base type, and still imports nothing from a module's `Exceptions` namespace (design L4).
 *
 * All user-facing copy is localized through the host's {@see i18nKey()} (invariant 12). The concrete
 * exceptions above are named in PROSE, never as a `{@see}` type, so Pint's fully_qualified_strict_types
 * cannot re-add a forbidden `Catalog\Exceptions` import (lessons.md, 2026-06-20).
 */
trait SurfacesDomainActions
{
    /**
     * The `operator_console.<key>` root for the host entity's copy (e.g. `product_master`, `format`). It
     * drives every action label, affordance and notification this concern renders.
     */
    abstract protected function i18nKey(): string;

    /**
     * Build a uniform write-through lifecycle action. It routes the operator's click to the per-entity
     * domain-action invocation through {@see surfaceLifecycleOutcome()} — never an Eloquent write (the
     * no-Eloquent-write rule, task 1.2). The label resolves `operator_console.<entity>.actions.<verb>` with the
     * verb snake-cased, so a camelCase action id (e.g. `retireCascade`) maps to the `retire_cascade` key; the
     * success notification resolves `…notifications.<successKey>`; a domain rejection surfaces a danger
     * notification. An optional notes form and an optional confirmation affordance
     * (`operator_console.<entity>.<confirmationKey>`) are attached when supplied.
     *
     * `$successKey` is a FIXED key for the verbs with one lawful outcome, or — for a verb the domain may
     * lawfully complete in more than one way — a Closure handed the domain action's return value and naming the
     * key for that outcome (design D11). The two paths differ only in WHEN the key is known: a fixed key
     * resolves to its title here, an outcome-aware one can only resolve after the action returns, so it is
     * deferred into {@see surfaceLifecycleOutcome()} as a title resolver.
     *
     * @param  string  $verb  the Filament action id (also the label key, snake-cased)
     * @param  string|Closure(mixed): string  $successKey  the `notifications.<successKey>` suffix for the success
     *                                                     title — fixed, or resolved from the action's return value
     * @param  Closure(Model, string): mixed  $invoke  the per-entity domain-action invocation (record, notes)
     * @param  array<int, Component>|null  $form  optional action form (e.g. reject notes)
     * @param  string|null  $confirmationKey  optional `operator_console.<entity>.<confirmationKey>` affordance copy
     */
    protected function lifecycleAction(
        string $verb,
        string|Closure $successKey,
        Closure $invoke,
        ?array $form = null,
        ?string $confirmationKey = null,
    ): Action {
        $i18nKey = $this->i18nKey();

        $action = Action::make($verb)
            ->label((string) __("operator_console.{$i18nKey}.actions.".Str::snake($verb)))
            ->action(
                /** @param  array<string, mixed>  $data */
                function (Model $record, array $data) use ($invoke, $i18nKey, $successKey): void {
                    // The reject form's `required` Textarea makes `notes` a present string on its happy path; a
                    // form-less action receives `data = []` (Filament's getData()), so `notes` narrows to ''.
                    $notes = is_string($data['notes'] ?? null) ? $data['notes'] : '';

                    $successTitle = $successKey instanceof Closure
                        ? static fn (mixed $outcome): string => (string) __("operator_console.{$i18nKey}.notifications.".$successKey($outcome))
                        : (string) __("operator_console.{$i18nKey}.notifications.{$successKey}");

                    $this->surfaceLifecycleOutcome(
                        fn () => $invoke($record, $notes),
                        $successTitle,
                    );
                }
            );

        if ($form !== null) {
            $action = $action->form($form);
        }

        if ($confirmationKey !== null) {
            $action = $action
                ->requiresConfirmation()
                ->modalDescription((string) __("operator_console.{$i18nKey}.{$confirmationKey}"));
        }

        return $action;
    }

    /**
     * Run a domain lifecycle action and surface its outcome to the operator. On completion: a success
     * notification, titled either by the fixed `$successTitle` or by a resolver READING the value the action
     * returned (design D11). When the domain REJECTS the transition — an out-of-state transition, an
     * approval-governance / producer-gate / activation-cascade / reference-integrity / Hero-Package-capacity
     * violation (all extend RuntimeException) — a danger notification carrying the action's already-localized
     * message, leaving the record unchanged (the rejecting action's transaction rolled back). The console never
     * re-checks the from-state or any gate itself (design L4); it catches the rejection by base type so it
     * imports nothing from a module's `Exceptions` namespace (the {Models, Actions} surface, task 1.3) and so
     * surfaces every module exception, present and future, by construction.
     *
     * A title resolver runs OUTSIDE the try: it is console code, and a console-side failure is a programmer
     * error to be raised, never a domain rejection to be dressed up as one.
     *
     * @param  Closure(): mixed  $run  invokes the domain action
     * @param  string|Closure(mixed): string  $successTitle  a fixed title, or a resolver reading `$run()`'s return value
     */
    protected function surfaceLifecycleOutcome(Closure $run, string|Closure $successTitle): void
    {
        try {
            $outcome = $run();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title((string) __('operator_console.'.$this->i18nKey().'.notifications.action_failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($successTitle instanceof Closure ? $successTitle($outcome) : $successTitle)
            ->send();
    }

    /**
     * Narrow the Filament page record to the concrete {@see Model} the typed domain action consumes — the one
     * place a generic `Model` becomes a `ProductMaster`/`Format`/… so each per-entity invocation stays a clean,
     * fully-typed one-liner. Filament always injects the page's own record, so the guard never trips in
     * practice; it exists to satisfy the type system (and to fail loudly on a future mis-wire).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    protected function recordOf(string $class, Model $record): Model
    {
        if (! $record instanceof $class) {
            throw new LogicException(sprintf('Expected a %s record, got %s.', $class, $record::class));
        }

        return $record;
    }
}
