<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewProducer — the Producer console view page (operator-console-parties-producer, task 1.1; design D1).
 *
 * This change reuses the operator-console kit at the TRAIT level for non-catalog lifecycles (ADR
 * 2026-06-20): the view page extends Filament's {@see ViewRecord} directly (NOT the catalog-shaped
 * `OperatorConsoleViewRecord`, whose fixed five-verb governance does not fit Producer's `draft → active →
 * retired` FSM) and will `use SurfacesDomainActions` to assemble its OWN verb set. The read surface (the
 * infolist) comes from {@see ProducerResource}; the lifecycle header actions — activate/retire (task 3.1) and
 * the four KYC verbs (task 4.1) — land in their own tasks. Until then this is the read-only Producer detail
 * page.
 */
class ViewProducer extends ViewRecord
{
    protected static string $resource = ProducerResource::class;
}
