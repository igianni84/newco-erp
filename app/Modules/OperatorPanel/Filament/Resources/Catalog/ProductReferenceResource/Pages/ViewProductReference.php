<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\RejectProductReferenceReview;
use App\Modules\Catalog\Actions\ReopenProductReference;
use App\Modules\Catalog\Actions\RetireProductReference;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProductReference — the Product Reference console view page (operator-console-catalog-spine, task 3.2;
 * design L1/L4; ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the
 * FIVE uniform lifecycle header actions (submit · reject · activate · retire · reopen) from
 * {@see lifecycleInvocations()}, and a Product Reference adds NO divergence — its parent gate (TWO parents:
 * Variant AND Format) is surfaced FOR FREE (it does not add a header action), and (unlike Product Master) it
 * has NO cascade-retire affordance (scope guard: cascade-retire is Master-only, no `RetireProductReferenceCascade`
 * Action ships). So this page does not override `getHeaderActions()`.
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, the activation-cascade gate (activating a PR whose
 * parent Variant OR Format is not `active` throws the domain's {@see ActivateProductReference}
 * `ActivationCascadeViolation`, surfaced via `catalog.gate.parent_not_active`), AND the retire
 * reference-integrity block: retiring a PR still referenced by an `active` Sellable or Composite SKU throws the
 * domain's {@see RetireProductReference} `RetirementReferenceIntegrityViolation`, which the wrapper renders as a
 * danger notification (`catalog.retirement.blocked_by_active_references`) — the console re-checks NONE of them
 * (design L4). There is NO field-edit (the Catalog backend ships no update action — lifecycle TRANSITIONS only,
 * proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewProductReference extends OperatorConsoleViewRecord
{
    protected static string $resource = ProductReferenceResource::class;

    protected function i18nKey(): string
    {
        return 'product_reference';
    }

    /**
     * The five uniform lifecycle invocations for a Product Reference, each routing to its typed Catalog action
     * (the {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also
     * carries the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see ProductReference}
     * so each call is fully typed. `activate` routes to {@see ActivateProductReference}, whose activation-cascade
     * gate (parent Variant AND Format active) the wrapper surfaces; `retire` routes to
     * {@see RetireProductReference}, whose reference-integrity block (no `active` Sellable/Composite SKU) the
     * wrapper surfaces — this page neither evaluates nor branches on either.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitProductReferenceForReview::class)->handle($this->recordOf(ProductReference::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectProductReferenceReview::class)->handle($this->recordOf(ProductReference::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
        ];
    }
}
