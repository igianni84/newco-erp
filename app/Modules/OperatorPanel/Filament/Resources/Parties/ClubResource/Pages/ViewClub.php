<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewClub — the Club console view page (operator-console-parties-supply-side, task 2.2; design D1).
 *
 * Scaffolded here as a BARE read-only {@see ViewRecord} so {@see ClubResource::getPages()} can boot (a Filament
 * Resource eagerly references each page class at registration — the read surface cannot ship without all three
 * pages existing; design Risks). It renders the read-only infolist defined on {@see ClubResource}. The lifecycle
 * verbs — sunset/close, assembled with the `SurfacesDomainActions` trait (NOT the catalog
 * `OperatorConsoleViewRecord`, D1) — land in task 4.1.
 */
class ViewClub extends ViewRecord
{
    protected static string $resource = ClubResource::class;
}
