<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages;

use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\RejectSellableSkuReview;
use App\Modules\Catalog\Actions\ReopenSellableSku;
use App\Modules\Catalog\Actions\RetireSellableSku;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewSellableSku — the Sellable SKU console view page (operator-console-catalog-spine, task 3.3; design L1/L4;
 * ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the FIVE uniform
 * lifecycle header actions (submit · reject · activate · retire · reopen) from {@see lifecycleInvocations()}, and
 * a Sellable SKU adds NO divergence — its parent gate (TWO parents: Product Reference AND Case Configuration) is
 * surfaced FOR FREE (it does not add a header action), and (unlike Product Master) it has NO cascade-retire
 * affordance (scope guard: cascade-retire is Master-only, no `RetireSellableSkuCascade` Action ships). It is a
 * LEAF within Module 0, so retire carries no within-catalog reference-integrity block either. So this page does
 * not override `getHeaderActions()`.
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, and the activation-cascade gate (activating a SKU
 * whose parent Product Reference OR Case Configuration is not `active` throws the domain's
 * {@see ActivateSellableSku} `ActivationCascadeViolation`, surfaced via `catalog.gate.parent_not_active`) — the
 * console re-checks NONE of them (design L4). There is NO field-edit (the Catalog backend ships no update action —
 * lifecycle TRANSITIONS only, proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewSellableSku extends OperatorConsoleViewRecord
{
    protected static string $resource = SellableSkuResource::class;

    protected function i18nKey(): string
    {
        return 'sellable_sku';
    }

    /**
     * The five uniform lifecycle invocations for a Sellable SKU, each routing to its typed Catalog action (the
     * {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also carries
     * the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see SellableSku} so each call
     * is fully typed. `activate` routes to {@see ActivateSellableSku}, whose activation-cascade gate (parent
     * Product Reference AND Case Configuration active) the wrapper surfaces; `retire` routes to
     * {@see RetireSellableSku}, a leaf retire with no within-catalog block — this page neither evaluates nor
     * branches on the gate.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitSellableSkuForReview::class)->handle($this->recordOf(SellableSku::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectSellableSkuReview::class)->handle($this->recordOf(SellableSku::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateSellableSku::class)->handle($this->recordOf(SellableSku::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireSellableSku::class)->handle($this->recordOf(SellableSku::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenSellableSku::class)->handle($this->recordOf(SellableSku::class, $record)),
        ];
    }
}
