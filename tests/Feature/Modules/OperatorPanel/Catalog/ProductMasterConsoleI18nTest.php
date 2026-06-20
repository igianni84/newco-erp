<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

// Task 6.1 (operator-console-catalog-master; design L8; ADR 2026-06-19; spec — Operator console copy is
// localized in EN and IT; invariant 12 — no hardcoded user-facing strings). The console's UI copy lives in
// the `operator_console` translation group (seeded 2.1, extended 3.1/4.1/4.2/5.1/5.2); every Filament class
// already routes its labels/affordances/notifications through __('operator_console.…').
//
// This is the capability-close i18n guard. It proves the localization end-to-end COMPOSITIONALLY:
//   (1) the token scan proves EVERY user-facing copy sink in the OperatorPanel\Filament classes receives a
//       __()/trans()/(string)-cast/variable argument — never a hardcoded literal (invariant 12); and
//   (2) the locale tests prove those `operator_console.*` keys resolve to authored Italian under `it` and
//       fall back per-key to English when absent (DEC-127).
// Together: a console whose every label is a translation key, over a group that renders Italian ⇒ the
// rendered console renders Italian. The live end-to-end render across the whole flow is the 6.2 chain test.
//
// Feature test (the translator/container must be booted — Pest binds the Laravel TestCase only in
// tests/Feature); no DB is touched (pure locale + static source scan), so no RefreshDatabase.
//
// NB domain rejection bodies (a Catalog action's exception message, surfaced as a notification body) come
// from lang/en/catalog.php — which is EN-only in this repo (no lang/it/catalog.php; same for parties.php).
// Under `it` those domain messages fall back per-key to EN (DEC-127). Authoring the IT Catalog group is the
// Catalog module's own i18n concern, out of this operator-surface change's scope (design: the Catalog
// backend is not modified here).

/**
 * Tokenise PHP $source and return every hardcoded string literal passed as the FIRST argument to a
 * user-facing Filament copy sink (->label('…'), ->title('…'), ->modalDescription('…'), …). A sink whose
 * first argument is a __()/trans() call, a `(string)` cast (wrapping __()), a variable or a nested call is
 * NOT flagged — only a bare quoted literal at a copy sink is a hardcoded-copy violation (invariant 12).
 * Component/action *names* (Action::make('submit')), field keys ('producer_id'), route paths ('/create') and
 * column attributes are not copy sinks, so their literals are correctly ignored.
 *
 * @return list<string> offending "sink('literal')" occurrences; empty means clean
 */
function scanOperatorConsoleHardcodedSinks(string $source): array
{
    $sinks = [
        'label', 'placeholder', 'helperText', 'hint', 'tooltip',
        'title', 'body', 'description', 'heading', 'subheading',
        'modalHeading', 'modalDescription', 'modalSubmitActionLabel', 'modalCancelActionLabel',
        'successNotificationTitle', 'failureNotificationTitle',
        'emptyStateHeading', 'emptyStateDescription',
    ];

    $tokens = token_get_all($source);
    $count = count($tokens);

    /** @var list<string> $violations */
    $violations = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        // A sink call is `-> methodName (` — anchor on the object operator, then read forward.
        if (! is_array($token) || $token[0] !== T_OBJECT_OPERATOR) {
            continue;
        }

        $methodIndex = operatorConsoleI18nNextMeaningfulToken($tokens, $i);
        if ($methodIndex === null) {
            continue;
        }

        $methodToken = $tokens[$methodIndex];
        if (! is_array($methodToken) || $methodToken[0] !== T_STRING || ! in_array($methodToken[1], $sinks, true)) {
            continue;
        }

        $parenIndex = operatorConsoleI18nNextMeaningfulToken($tokens, $methodIndex);
        if ($parenIndex === null || $tokens[$parenIndex] !== '(') {
            continue;
        }

        $argIndex = operatorConsoleI18nNextMeaningfulToken($tokens, $parenIndex);
        if ($argIndex === null) {
            continue;
        }

        $argToken = $tokens[$argIndex];
        if (is_array($argToken) && $argToken[0] === T_CONSTANT_ENCAPSED_STRING) {
            $violations[] = $methodToken[1].'('.$argToken[1].')';
        }
    }

    return $violations;
}

/**
 * Index of the next non-whitespace, non-comment token after $from, or null at end of stream.
 *
 * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
 */
function operatorConsoleI18nNextMeaningfulToken(array $tokens, int $from): ?int
{
    $count = count($tokens);

    for ($i = $from + 1; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

it('routes every user-facing string in the OperatorPanel Filament classes through the operator_console group', function () {
    $files = array_filter(
        File::allFiles(app_path('Modules/OperatorPanel/Filament')),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php',
    );

    // Guard against a vacuous pass if the surface ever moves: there ARE Filament classes to scan.
    expect($files)->not->toBeEmpty();

    $violations = [];
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        assert(is_string($source)); // narrow string|false for PHPStan (tests/ is analysed)

        foreach (scanOperatorConsoleHardcodedSinks($source) as $hit) {
            $violations[] = $file->getFilename().' → '.$hit;
        }
    }

    // Every console label/affordance/notification routes through __(); none is a hardcoded literal.
    expect($violations)->toBe([]);
});

it('flags a hardcoded literal at a copy sink but not a localized or non-copy argument (scanner non-vacuity)', function () {
    // A planted violation MUST be detected, else the clean-tree pass above is vacuous (the 1.2 red→green
    // discipline). Both copy sinks carry bare literals; the action *name* make('danger') is not a sink.
    $violating = <<<'PHP'
<?php
use Filament\Actions\Action;
Action::make('danger')
    ->label('Hardcoded action label')
    ->modalDescription('Hardcoded confirmation copy');
PHP;

    expect(scanOperatorConsoleHardcodedSinks($violating))->toBe([
        "label('Hardcoded action label')",
        "modalDescription('Hardcoded confirmation copy')",
    ]);

    // The mirror: a sink fed __()/(string)__()/trans()/a variable is clean; make() (a name) is never a sink.
    $localized = <<<'PHP'
<?php
use Filament\Actions\Action;
Action::make('safe')
    ->label((string) __('operator_console.product_master.actions.activate'))
    ->modalDescription(trans('operator_console.product_master.affordance.second_actor'))
    ->title($successTitle)
    ->body($exception->getMessage());
PHP;

    expect(scanOperatorConsoleHardcodedSinks($localized))->toBe([]);
});

it('renders console copy in Italian when the operator locale is it', function (string $key) {
    App::setLocale('it');

    // Genuinely authored in `it` (no fallback — the third Lang::has arg is false) AND distinct from the
    // English value, so this proves Italian rendering, not the EN fallback firing.
    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(__($key))->toBe(trans($key, [], 'it'))
        ->and(__($key))->not->toBe(trans($key, [], 'en'));
})->with([
    'operator_console.product_master.columns.name',
    'operator_console.product_master.columns.lifecycle_state',
    'operator_console.product_master.fields.appellation',
    'operator_console.product_master.actions.submit',
    'operator_console.product_master.actions.activate',
    'operator_console.product_master.actions.retire_cascade',
    'operator_console.product_master.affordance.second_actor',
    'operator_console.product_master.affordance.cascade_warning',
    'operator_console.product_master.notifications.activated',
    'operator_console.product_master.notifications.action_failed',
    'operator_console.product_master.producer_unprojected',
]);

it('falls back to the English value when a console key is absent in Italian', function () {
    App::setLocale('it');

    // product_master.label is an English-invariant domain term (CONTEXT.md): authored only in `en` and
    // intentionally omitted from `it` (per-key fallback, DEC-127). Under `it` the key is genuinely absent
    // (the non-vacuity guard) yet still resolves — to the English baseline value, per key (not the raw key).
    expect(Lang::has('operator_console.product_master.label', 'it', false))->toBeFalse()
        ->and(__('operator_console.product_master.label'))->toBe(trans('operator_console.product_master.label', [], 'en'))
        ->and(__('operator_console.product_master.label'))->toBe('Product Master')
        ->and(__('operator_console.product_master.label'))->not->toBe('operator_console.product_master.label');
});

it('keeps every Italian console key backed by an English baseline key', function () {
    $en = trans('operator_console', [], 'en');
    $it = trans('operator_console', [], 'it');

    assert(is_array($en) && is_array($it)); // a group resolves to its array (narrow string|array for PHPStan)

    $enKeys = array_keys(Arr::dot($en));
    $itKeys = array_keys(Arr::dot($it));

    // English is the final fallback baseline (DEC-127): every authored Italian key must have an English
    // counterpart, so no Italian copy dangles without a fallback and a typo'd `it` key is caught here.
    expect(array_values(array_diff($itKeys, $enKeys)))->toBe([]);
});
