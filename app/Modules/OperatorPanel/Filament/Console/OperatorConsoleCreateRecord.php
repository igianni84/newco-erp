<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * OperatorConsoleCreateRecord — the shared write-through base create page for every catalog operator console
 * (operator-console-catalog-spine, task 1.2; ADR 2026-06-20; design L1/L5; it resolves the predecessor's
 * design L9 deferral).
 *
 * The operator console's one law (ADR 2026-06-19): it READS module models and WRITES only through module
 * domain actions — never `$model->save()`. This base owns the create-side discipline so each `<Entity>Create`
 * page supplies only its one irreducible per-entity line ({@see createViaAction()}) and the field a create
 * rejection lands on ({@see createRejectionField()}):
 *   - {@see handleRecordCreation()} FULLY overrides Filament's default `new Model($data); $record->save()`
 *     (which the no-Eloquent-write PHPStan rule, task 1.2, would fail) — it delegates the write to the
 *     per-entity {@see createViaAction()} and wraps it in the common create-rejection→form-error catch.
 *   - A localized domain {@see RuntimeException} thrown on the create path (e.g. Product Master's
 *     BR-Identity-1 dedup, Composite SKU's `< 2 constituents`) is mapped to a `data.<field>` form error
 *     (design L5) — surfaced to the operator, not a 500. The message is already localized by the action
 *     (`lang/{en,it}/catalog.php`); the console renders it verbatim.
 *
 * The ONE special case is a create rejection with NO localized domain message — Product Reference's duplicate
 * `(variant, format)`, a framework `Illuminate\Database\UniqueConstraintViolationException`. That page catches
 * the framework exception inside its own {@see createViaAction()} and throws a `ValidationException` carrying a
 * console-owned localized message; because `ValidationException` is not a `RuntimeException`, it sails through
 * the catch here untouched, so the raw SQL string is never rendered (design L5).
 *
 * Like the other kit bases it lives under `Filament/Console/`, not the discovered `Filament/Pages`, so this
 * abstract base is never auto-discovered by the panel.
 */
abstract class OperatorConsoleCreateRecord extends CreateRecord
{
    /**
     * One clean create flow — no "create & create another"; every create routes through the domain action and
     * the simpler single path keeps the write-through surface minimal.
     */
    protected static bool $canCreateAnother = false;

    /**
     * The one irreducible per-entity line: narrow the validated form state to the Catalog action's typed
     * contract and route the write through `app(<CreateAction>::class)->handle(...)`, returning the new model.
     * It NEVER writes an Eloquent model directly (the no-Eloquent-write rule, task 1.2). Product Reference is
     * the lone entity that catches a framework exception here and re-raises it as a {@see ValidationException}.
     *
     * @param  array<string, mixed>  $data  the validated form state Filament passes to create
     */
    abstract protected function createViaAction(array $data): Model;

    /**
     * The form field a localized domain create-rejection is surfaced on (e.g. `name` for Product Master,
     * the constituents field for Composite SKU). Used by {@see handleRecordCreation()} to map the rejection
     * to `data.<field>`; for an entity whose create ships no domain guard it is a harmless safety net.
     */
    abstract protected function createRejectionField(): string;

    /**
     * Route the create through the per-entity domain action and surface a domain rejection as a form error.
     * Fully replaces Filament's default model-saving template (design L1/L5): no `$model->save()` here.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return $this->createViaAction($data);
        } catch (RuntimeException $exception) {
            // A localized domain rejection on the create path → a form field error, not an unhandled 500.
            // `data.<field>` is the form's state-path-qualified key Filament's assertHasFormErrors resolves to.
            throw ValidationException::withMessages([
                'data.'.$this->createRejectionField() => $exception->getMessage(),
            ]);
        }
    }
}
