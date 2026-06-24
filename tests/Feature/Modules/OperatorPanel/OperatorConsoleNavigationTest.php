<?php

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

// operator-console-navigation-grouping: the operator panel's twelve consoles render as TWO ordered, localized
// sidebar groups — Catalog (Module 0 / PIM) above Parties (Module K) — instead of one flat alphabetical list.
// Grouping is unspecified by the spec (a free IA choice); these assertions ARE the source of truth for the
// decision, so a future resource silently dropping out of a group, or the order drifting, fails here.
//
// The mechanism (OperatorConsoleNavigationGroup, a HasLabel enum returned by each resource's getNavigationGroup):
//   - group ORDER = enum case-declaration order (Catalog, then Parties) — locale-independent, needs no panel
//     registration (Filament sorts enum-keyed groups by cases() position);
//   - group LABEL = getLabel() off operator_console.navigation_group.<case> — localized per operator locale.
// Feature test: the translator/container must be booted (Pest binds the Laravel TestCase under tests/Feature);
// no DB is touched (static reads + locale), so no RefreshDatabase.

/**
 * The twelve operator consoles in their intended sidebar order, each with its module group and within-group
 * navigationSort — the single source of truth the assertions below lock.
 *
 * @return array<string, array{class-string<OperatorConsoleResource>, OperatorConsoleNavigationGroup, int}>
 */
function consoleNavigationMap(): array
{
    return [
        // Catalog (Module 0 / PIM) — PIM hierarchy order: Master → Variant → Format → Reference → Sellable →
        // Composite → Case Configuration.
        'Product Master' => [ProductMasterResource::class, OperatorConsoleNavigationGroup::Catalog, 1],
        'Product Variant' => [ProductVariantResource::class, OperatorConsoleNavigationGroup::Catalog, 2],
        'Format' => [FormatResource::class, OperatorConsoleNavigationGroup::Catalog, 3],
        'Product Reference' => [ProductReferenceResource::class, OperatorConsoleNavigationGroup::Catalog, 4],
        'Sellable SKU' => [SellableSkuResource::class, OperatorConsoleNavigationGroup::Catalog, 5],
        'Composite SKU' => [CompositeSkuResource::class, OperatorConsoleNavigationGroup::Catalog, 6],
        'Case Configuration' => [CaseConfigurationResource::class, OperatorConsoleNavigationGroup::Catalog, 7],
        // Parties (Module K).
        'Customer' => [CustomerResource::class, OperatorConsoleNavigationGroup::Parties, 1],
        'Profile' => [ProfileResource::class, OperatorConsoleNavigationGroup::Parties, 2],
        'Club' => [ClubResource::class, OperatorConsoleNavigationGroup::Parties, 3],
        'Producer' => [ProducerResource::class, OperatorConsoleNavigationGroup::Parties, 4],
        'Producer Agreement' => [ProducerAgreementResource::class, OperatorConsoleNavigationGroup::Parties, 5],
    ];
}

it('assigns each operator console to its module navigation group', function (string $class, OperatorConsoleNavigationGroup $group, int $sort): void {
    /** @var class-string<OperatorConsoleResource> $class */
    expect($class::getNavigationGroup())->toBe($group);
})->with(consoleNavigationMap());

it('orders each console within its group by the domain hierarchy', function (string $class, OperatorConsoleNavigationGroup $group, int $sort): void {
    /** @var class-string<OperatorConsoleResource> $class */
    expect($class::getNavigationSort())->toBe($sort);
})->with(consoleNavigationMap());

it('declares the groups so Catalog renders above Parties', function (): void {
    // Filament orders enum-keyed groups by their cases() position, so this declaration order IS the sidebar order.
    expect(OperatorConsoleNavigationGroup::cases())
        ->toBe([OperatorConsoleNavigationGroup::Catalog, OperatorConsoleNavigationGroup::Parties]);
});

it('authors both navigation group labels in the English baseline', function (): void {
    // The third Lang::has arg is false: genuinely authored in en, not merely resolvable via fallback — so neither
    // group ever renders as a raw translation key.
    expect(Lang::has('operator_console.navigation_group.catalog', 'en', false))->toBeTrue()
        ->and(Lang::has('operator_console.navigation_group.parties', 'en', false))->toBeTrue();
});

it('localizes the navigation group labels per operator locale', function (): void {
    // Unlike the English-invariant entity labels (Product Master, Customer…), the module group names localize.
    App::setLocale('en');
    expect(OperatorConsoleNavigationGroup::Catalog->getLabel())->toBe('Catalog')
        ->and(OperatorConsoleNavigationGroup::Parties->getLabel())->toBe('Parties');

    App::setLocale('it');
    expect(OperatorConsoleNavigationGroup::Catalog->getLabel())->toBe('Catalogo')
        ->and(OperatorConsoleNavigationGroup::Parties->getLabel())->toBe('Anagrafiche');
});
