<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages;

use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\RejectFormatReview;
use App\Modules\Catalog\Actions\ReopenFormat;
use App\Modules\Catalog\Actions\RetireFormat;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewFormat — the Format console view page (operator-console-catalog-spine, task 2.1; design L1/L4; ADR
 * 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the FIVE uniform
 * lifecycle header actions (submit · reject · activate · retire · reopen) from {@see lifecycleInvocations()},
 * and Format adds NO divergence — it is a standalone reference entity with no parent gate and (unlike Product
 * Master) NO cascade-retire affordance (scope guard: cascade-retire is Master-only, no `RetireFormatCascade`
 * Action ships). So this page does not override `getHeaderActions()`.
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard and the
 * Creator → Reviewer → Approver separation-of-duties floor — it reimplements none of them (design L4). There
 * is NO field-edit (the Catalog backend ships no update action — lifecycle TRANSITIONS only, proposal
 * slice-boundary). All copy is localized (invariant 12).
 */
class ViewFormat extends OperatorConsoleViewRecord
{
    protected static string $resource = FormatResource::class;

    protected function i18nKey(): string
    {
        return 'format';
    }

    /**
     * The five uniform lifecycle invocations for Format, each routing to its typed Catalog action (the
     * {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also carries
     * the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see Format} so each call is
     * fully typed.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitFormatForReview::class)->handle($this->recordOf(Format::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectFormatReview::class)->handle($this->recordOf(Format::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateFormat::class)->handle($this->recordOf(Format::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireFormat::class)->handle($this->recordOf(Format::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenFormat::class)->handle($this->recordOf(Format::class, $record)),
        ];
    }
}
