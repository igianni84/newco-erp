<?php

declare(strict_types=1);

namespace Tests\Architecture\Fixtures;

use App\Modules\Catalog\Models\ProductMaster;

/**
 * Fixture for NoEloquentWriteInOperatorPanelRuleTest. NOT real code: it is excluded from the main
 * phpstan run (phpstan.neon excludePaths) and exists only to pin the operator-console architecture
 * rule's precision. Every write line below carries a trailing `flag` comment and MUST be reported
 * by the rule; the read lines carry no such marker and MUST NOT be. The test derives the expected
 * line set from those markers, so the layout can shift freely without breaking the assertion.
 */
final class OperatorPanelEloquentWriteFixture
{
    public function instanceWrites(ProductMaster $master): void
    {
        $master->save();                    // flag
        $master->saveQuietly();             // flag
        $master->update(['name' => 'x']);   // flag
        $master->updateQuietly(['n' => 1]); // flag
        $master->delete();                  // flag
        $master->forceDelete();             // flag
        $master->fill(['name' => 'x']);     // flag
        $master->setAttribute('name', 'x'); // flag
    }

    public function staticWrites(): void
    {
        ProductMaster::create(['name' => 'x']); // flag
        ProductMaster::insert(['name' => 'x']); // flag
    }

    public function allowedReads(ProductMaster $master): string
    {
        $name = $master->name;            // read — allowed
        ProductMaster::query()->count();  // read — allowed
        $master->refresh();               // read — allowed

        return (string) $name;
    }
}
