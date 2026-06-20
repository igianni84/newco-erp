<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tests\PHPStan\Rules\NoEloquentWriteInOperatorPanelRule;

/**
 * Pins the operator-console architecture rule {@see NoEloquentWriteInOperatorPanelRule} (design
 * L4; ADR 2026-06-19). The rule is the CI half of the read-binding / write-through-actions
 * discipline: in production it is registered in phpstan.neon scoped to the console surface
 * (`app/Modules/OperatorPanel/Filament/`), so a console class writing an Eloquent model directly
 * fails `type_check`. Here it runs against a fixture (scoped by the `Architecture/Fixtures`
 * needle) so the red→green proof is an automated regression guard, not a one-off manual check.
 *
 * @extends RuleTestCase<NoEloquentWriteInOperatorPanelRule>
 */
final class NoEloquentWriteInOperatorPanelRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        // Mirror the production scope onto the fixture path (production scope is the console
        // surface app/Modules/OperatorPanel/Filament/).
        return new NoEloquentWriteInOperatorPanelRule(['Architecture/Fixtures']);
    }

    public function test_it_flags_every_eloquent_write_on_a_model_and_no_read(): void
    {
        $fixture = __DIR__.'/Fixtures/OperatorPanelEloquentWriteFixture.php';

        $errors = $this->gatherAnalyserErrors([$fixture]);

        // Every reported error is this rule's (none leaked from elsewhere).
        foreach ($errors as $error) {
            $this->assertSame('operatorPanel.noEloquentWrite', $error->getIdentifier());
        }

        $flaggedLines = array_map(static fn ($error) => $error->getLine(), $errors);
        sort($flaggedLines);

        // Exactly the lines tagged `// flag` in the fixture — the 10 planted writes (instance
        // save/saveQuietly/update/updateQuietly/delete/forceDelete/fill/setAttribute + static
        // create/insert) and none of the three reads. Derived from the markers so a layout shift
        // can't desync the assertion.
        $this->assertSame($this->markedLines($fixture), $flaggedLines);
        $this->assertCount(10, $flaggedLines);
    }

    /**
     * Line numbers (1-based) of every line in $file tagged with a trailing `// flag` marker.
     *
     * @return list<int>
     */
    private function markedLines(string $file): array
    {
        $contents = file($file);
        $lines = [];

        if ($contents === false) {
            return $lines;
        }

        foreach ($contents as $index => $line) {
            if (str_contains($line, '// flag')) {
                $lines[] = $index + 1;
            }
        }

        return $lines;
    }
}
