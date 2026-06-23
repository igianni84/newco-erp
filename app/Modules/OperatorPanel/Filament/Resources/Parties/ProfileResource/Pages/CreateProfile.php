<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The write-through Create page for a Profile (operator-console-parties-membership; design D6).
 *
 * Scaffolded in task 1.2 so the resource's `getPages()` boots (the eager page-reference coupling — design Risks):
 * it extends {@see OperatorConsoleCreateRecord} so the no-Eloquent-write discipline (the base's
 * `handleRecordCreation()` delegates to {@see createViaAction()}, never `$model->save()`) is in place. The
 * write-through wiring — the Customer + Club selects on {@see ProfileResource::form()} routed into the Parties
 * `CreateProfile` action (born `Applied`, recording one `ProfileCreated`; a duplicate non-terminal pair surfaced
 * as `DuplicateProfileForClub`) — lands in task 2.1. No create path is exercised until then.
 */
class CreateProfile extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProfileResource::class;

    /**
     * The form field a localized create-rejection surfaces on — the Club select (a `DuplicateProfileForClub`
     * lands here, task 2.1). Inert until the create surface is wired.
     */
    protected function createRejectionField(): string
    {
        return 'club_id';
    }

    /**
     * The write-through create routes through the Parties `CreateProfile` action in task 2.1; until then the path
     * is unwired (no create form, no test drives it). A call here is a programming error, not a form rejection
     * (a `LogicException` propagates past the base's `RuntimeException` create-rejection catch).
     *
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        throw new LogicException('Profile create write-through is wired in task 2.1.');
    }
}
