<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Platform\Audit\AuditRecord;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * OperatorConsoleViewRecord — the shared base view page for every catalog operator console
 * (operator-console-catalog-spine, task 1.1; ADR 2026-06-20; design L1/L2/L4; it resolves the predecessor's
 * design L9 deferral). It renders the FIVE uniform lifecycle header actions — submit · reject (notes) ·
 * activate (second-actor affordance) · retire · reopen — from the per-entity domain-action invocations the
 * subclass supplies in {@see lifecycleInvocations()}, wiring each through the {@see SurfacesDomainActions}
 * concern so a write routes to the module's domain action and a domain rejection surfaces as a danger
 * notification (never an Eloquent write — the no-Eloquent-write rule, task 1.2; never a reimplemented gate —
 * design L4).
 *
 * A per-entity view page sets `$resource`, returns its `operator_console.<entity>` root from {@see i18nKey()}
 * and its five invocations from {@see lifecycleInvocations()}. Entity-specific extras (e.g. Product Master's
 * operator-driven cascade retire) are appended by overriding {@see getHeaderActions()} and spreading
 * `parent::getHeaderActions()` — the base owns the uniform five, the subclass owns only its divergence
 * (design L6). The read-only list/infolist lives on the {@see \Filament\Resources\Resource}, not here.
 *
 * Alongside the verb-shaped lifecycle actions the base owns the FORM-shaped ones: {@see contentEditAction()}
 * builds a content-edit modal (catalog-module-0-completeness-sweep design D8) whose write routes through a
 * Catalog content-edit Action. There are still NO Edit PAGES — the read-projection discipline stands (ADR
 * 2026-06-19); an edit is a modal header action like every other write-through.
 */
abstract class OperatorConsoleViewRecord extends ViewRecord
{
    use SurfacesDomainActions;

    /**
     * The per-entity domain-action invocations keyed by lifecycle verb — `submit`, `reject`, `activate`,
     * `retire`, `reopen`. Each closure receives the page record and the reject notes (`''` for the form-less
     * verbs) and routes to the entity's typed Catalog action via {@see recordOf()} — e.g.
     * `fn (Model $record) => app(SubmitFormatForReview::class)->handle($this->recordOf(Format::class, $record))`.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    abstract protected function lifecycleInvocations(): array;

    /**
     * The five uniform lifecycle header actions. Reject carries the notes form; activate carries the
     * "second actor required" confirmation affordance (the SoD floor the domain enforces and this surfaces,
     * design L4). A subclass appends entity-specific actions by spreading `parent::getHeaderActions()`.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $invocations = $this->lifecycleInvocations();

        return [
            $this->lifecycleAction('submit', 'submitted', $invocations['submit']),
            $this->lifecycleAction('reject', 'rejected', $invocations['reject'], form: [
                Textarea::make('notes')
                    ->label((string) __('operator_console.'.$this->i18nKey().'.fields.rejection_notes'))
                    ->required(),
            ]),
            $this->lifecycleAction('activate', 'activated', $invocations['activate'], confirmationKey: 'affordance.second_actor'),
            $this->lifecycleAction('retire', 'retired', $invocations['retire']),
            $this->lifecycleAction('reopen', 'reopened', $invocations['reopen']),
        ];
    }

    /**
     * Build a FORM-shaped write-through action — a content-edit modal (catalog-module-0-completeness-sweep
     * design D8; spec — Operator edits catalog identity content through the console). The modal is prefilled
     * from the record by `$fill`, and on submit `$invoke` routes the validated form state into the entity's
     * Catalog content-edit Action — never an Eloquent write (the no-Eloquent-write rule).
     *
     * WHERE THE REJECTION LANDS is the one thing that distinguishes this from {@see lifecycleAction()}. A
     * verb-shaped write (submit, activate, retire) has no form, so the domain's localized rejection can only be
     * a danger notification. A form-shaped write DOES have a form, and one of its rejections — Product Master's
     * BR-Identity-1 dedup collision — is REQUIRED by the spec to surface as a form validation error. The console
     * cannot type-discriminate the rejections it catches (it imports nothing from a module's `Exceptions`
     * namespace — the {Models, Actions} surface, task 1.3), so every localized domain rejection on this path
     * lands UNIFORMLY on `$rejectionField` as a validation error, exactly as
     * {@see OperatorConsoleCreateRecord::handleRecordCreation()} does for a create. The modal stays open with the
     * operator's input intact and the domain's own message beneath the field; the entity, the audit log and the
     * domain-event log are untouched (the rejecting action's transaction rolled back). The spec permits this for
     * the state guard and the composition floors ("validation error or notification") and demands it for the
     * dedup collision — one shape serves all three, and the console still reimplements no gate (design L4).
     *
     * `InvalidArgumentException` (a `LogicException`, thrown by a page narrowing an impossible form payload)
     * deliberately sails past the catch: a programming bug is not a form error.
     *
     * The view needs NO re-read after a successful edit, and that is a property of the domain rather than a
     * coincidence: `recordOf()` hands the Action the page's own record instance, and the `CatalogContentEdit`
     * mechanism performs its locked re-read and its `UPDATE` on THAT instance — so the page's `version` and
     * content are the post-edit truth by the time the infolist renders (the per-type attribute sets it reads
     * through relations are lazy-loaded after the write, fresh). A `$record->refresh()` here would be a query
     * that changes nothing. The property is load-bearing, so it is asserted, not assumed: the happy-path test
     * reads `version` back off the PAGE's record, and reds if a future Action ever writes a copy instead.
     *
     * `$invoke` receives the form state as a bare `array<array-key, mixed>`, not the `array<string, mixed>` map
     * Filament's own `getData()` documents. That is deliberate: the value Filament hands the action closure
     * arrives through a native `array` parameter, so the string-keyed claim is unprovable at this boundary — and
     * it buys nothing, because a caller reads it by string literal (`$data['name']`) and must narrow each `mixed`
     * value anyway. Declaring what we can prove keeps the analyser honest instead of silenced.
     *
     * @param  string  $verb  the Filament action id (also the `actions.<verb>` label key, snake-cased)
     * @param  string  $successKey  the `notifications.<successKey>` suffix for the success title
     * @param  string  $rejectionField  the form field a localized domain rejection is surfaced on
     * @param  array<int, Component>  $form  the modal's form components
     * @param  Closure(Model): array<string, mixed>  $fill  the modal's prefill state, read off the record
     * @param  Closure(Model, array<array-key, mixed>): mixed  $invoke  routes the validated form state into the domain Action
     */
    protected function contentEditAction(
        string $verb,
        string $successKey,
        string $rejectionField,
        array $form,
        Closure $fill,
        Closure $invoke,
    ): Action {
        $i18nKey = $this->i18nKey();

        $handle = function (Model $record, array $data) use ($invoke, $i18nKey, $successKey, $rejectionField): void {
            try {
                $invoke($record, $data);
            } catch (RuntimeException $exception) {
                // A localized domain rejection → a form field error carrying the action's own message.
                throw ValidationException::withMessages([
                    $this->mountedActionErrorKey($rejectionField) => $exception->getMessage(),
                ]);
            }

            Notification::make()
                ->success()
                ->title((string) __("operator_console.{$i18nKey}.notifications.{$successKey}"))
                ->send();
        };

        return Action::make($verb)
            ->label((string) __("operator_console.{$i18nKey}.actions.".Str::snake($verb)))
            ->schema($form)
            ->fillForm(fn (Model $record): array => $fill($record))
            ->action($handle);
    }

    /**
     * The Livewire error-bag key a domain rejection lands on inside the CURRENTLY MOUNTED action's form. Filament
     * state-paths a mounted action's schema at `mountedActions.<nestingIndex>.data`, so a bare field name would
     * raise an error the modal renders nowhere. Rather than reconstruct that path from the index, read it off the
     * mounted schema itself — the same derivation Filament's own `assertHasFormErrors()` test helper performs, so
     * the assertion and the production error can never disagree. Falls back to the bare field name when no action
     * is mounted (unreachable from an action's own `->action()` closure; it exists to keep the return typed).
     */
    private function mountedActionErrorKey(string $field): string
    {
        $schemaName = $this->getMountedActionSchemaName();
        $statePath = $schemaName === null ? null : $this->getSchema($schemaName)?->getStatePath();

        return ($statePath === null || $statePath === '') ? $field : $statePath.'.'.$field;
    }

    /**
     * The REVIEW-FRESHNESS-RELEVANT catalog audit verbs — the ONLY rows the review-stale condition derives from
     * (RM-06 / canon MVP-DEC-019 + its edit leg; catalog-module-0-completeness-sweep design D4/D9). This mirrors
     * the domain's own verb set VALUE-FOR-VALUE; it is duplicated rather than imported because the console may
     * not reach into `Catalog\Lifecycle` (see the boundary note on {@see isReviewStale()}), and a uniformity test
     * pins the two derivations to agree on the same audit histories.
     *
     * @var list<string>
     */
    private const REVIEW_FRESHNESS_VERBS = ['submitted', 'resubmitted', 'rejected', 'identity_updated'];

    /**
     * The subset of the relevant verbs that leaves the entity REVIEW-STALE: an un-remediated rejection, or
     * review-governed identity content edited since the last review decision.
     *
     * @var list<string>
     */
    private const REVIEW_STALE_VERBS = ['rejected', 'identity_updated'];

    /**
     * Is the page record REVIEW-STALE — is its latest REVIEW-FRESHNESS-RELEVANT catalog audit action either an
     * un-remediated rejection or an un-re-reviewed identity edit (RM-06 / canon MVP-DEC-019 and its edit leg;
     * design D4/D9)? This is the derived read that gates the re-submit header action's `->visible()` on every
     * catalog console: re-submit is OFFERED exactly while the entity is review-stale and HIDDEN otherwise (a
     * redundant re-submit on a fresh `reviewed` entity is a harmless no-op the operator is not shown).
     *
     * It MIRRORS the Catalog block-gate's derivation (the domain's `ApprovalGovernance` review-freshness
     * assertion: among the entity's audit actions ending in `.submitted`/`.resubmitted`/`.rejected`/
     * `.identity_updated` the newest by id wins, and it is stale iff it ends `.rejected` or `.identity_updated`),
     * so the console offers re-submit exactly when the domain would block activation — but it never REIMPLEMENTS
     * the gate (the Catalog backend is the sole enforcement, design L4); it only reads the platform substrate to
     * decide what to surface. The verb FILTER is load-bearing: the catalog audit trail also carries enrichment
     * and whitelist maintenance rows, and a raw latest-action read would hide the re-submit button the moment one
     * of those landed on top of a rejection — while the domain went on blocking activation (design R3).
     *
     * Boundary: reads {@see AuditRecord} (platform, imported like Money/I18n), never a Catalog type — the
     * {Models, Actions} cross-module surface holds. The Catalog governance/mechanism classes are named in PROSE
     * (never a `{@see}` type), so Pint's fully_qualified_strict_types cannot re-add a forbidden
     * `Catalog\Lifecycle` import (lessons.md 2026-06-20). Rows are scoped by `entity_type` + `entity_id`: the
     * record's class basename is the canonical entity label the shared lifecycle mechanism stamps on every audit
     * row (a `ProductMaster` record → `ProductMaster`), and that label is globally unique to the catalog entity,
     * so no `module` predicate is needed (which also keeps `App\Modules\Module` off the console's imports). The
     * `is_int/is_string` key guard mirrors that mechanism (a spine entity always keys on a scalar); the
     * `is_string` action guard is load-bearing — an audit action column read returns `mixed`, and
     * `str_ends_with(null, …)` would TypeError under PHP 8.5.
     *
     * The `LIKE` prefilter is an over-approximation (`_` is a single-character wildcard on both engines), so the
     * PHP suffix pass over the newest-first candidates is the authoritative, exact filter — same construction,
     * and same rationale, as the domain read it mirrors.
     */
    protected function isReviewStale(): bool
    {
        $record = $this->getRecord();
        $key = $record->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return false;
        }

        $candidates = AuditRecord::query()
            ->where('entity_type', class_basename($record))
            ->where('entity_id', (string) $key)
            ->where(function (Builder $relevant): void {
                foreach (self::REVIEW_FRESHNESS_VERBS as $verb) {
                    $relevant->orWhere('action', 'like', '%.'.$verb);
                }
            })
            ->orderByDesc('id')
            ->pluck('action');

        foreach ($candidates as $action) {
            if (! is_string($action) || ! self::endsWithVerb($action, self::REVIEW_FRESHNESS_VERBS)) {
                continue;
            }

            return self::endsWithVerb($action, self::REVIEW_STALE_VERBS);
        }

        return false;
    }

    /**
     * Does this audit action end in `.<verb>` for any of the given verbs (the exact, engine-free suffix test)?
     *
     * @param  list<string>  $verbs
     */
    private static function endsWithVerb(string $action, array $verbs): bool
    {
        foreach ($verbs as $verb) {
            if (str_ends_with($action, '.'.$verb)) {
                return true;
            }
        }

        return false;
    }
}
