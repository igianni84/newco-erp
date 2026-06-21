<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewProducerAgreement — the ProducerAgreement console view page (operator-console-parties-supply-side, task
 * 7.2). A BARE read-only {@see ViewRecord} for now: it renders the read-only infolist defined on
 * {@see ProducerAgreementResource} and exists so the resource's `getPages()` (eagerly referenced at panel
 * registration) boots. The two status verbs (activate / terminate) are assembled here in task 9.1, when this page
 * gains the `SurfacesDomainActions` trait — mirroring the Producer and Club console view pages (design D1).
 */
class ViewProducerAgreement extends ViewRecord
{
    protected static string $resource = ProducerAgreementResource::class;
}
