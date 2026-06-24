<?php

use App\Modules\OperatorPanel\Filament\Clusters\CatalogSettings;
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
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

// operator-console-navigation-grouping + the operator-console UI pass (2026-06-24): the operator panel's consoles
// render as TWO ordered, localized sidebar groups — Catalog (Module 0) above Parties (Module K). The UI pass
// reshaped the IA the consoles expose, and THIS test is the source of truth for that decision:
//   - Catalog top-level: Product Master (with Variants nested inside it), Sellable SKU, Composite SKU, plus the
//     "Settings" cluster (Format · Case Configuration · Product Reference rendered as tabs);
//   - Product Variant is hidden from the sidebar — it is seen + created inside its parent Product Master;
//   - Parties top-level: Customer, the Profile queue relabelled "Memberships", Producer, Supplier;
//   - Club and Producer Agreement are hidden — seen + created inside their Producer.
// A future console silently dropping out of its group, the order drifting, or a nested console leaking back into
// the sidebar fails here.
//
// Feature test: the translator/container must be booted (Pest binds the Laravel TestCase under tests/Feature);
// no DB is touched (static reads + locale), so no RefreshDatabase.

it('assigns each top-level Catalog console to the Catalog group, ordered, sidebar-visible and un-clustered', function (string $class, int $sort): void {
    /** @var class-string<OperatorConsoleResource> $class */
    expect($class::getNavigationGroup())->toBe(OperatorConsoleNavigationGroup::Catalog)
        ->and($class::getNavigationSort())->toBe($sort)
        ->and($class::shouldRegisterNavigation())->toBeTrue()
        ->and($class::getCluster())->toBeNull();
})->with([
    'Product Master' => [ProductMasterResource::class, 1],
    'Sellable SKU' => [SellableSkuResource::class, 5],
    'Composite SKU' => [CompositeSkuResource::class, 6],
]);

it('clusters the Catalog reference consoles under the Settings cluster with no own sidebar group', function (string $class): void {
    /** @var class-string<OperatorConsoleResource> $class */
    // Clustered: placement is the cluster's (a flat tab strip), so the resource reports NO navigation group.
    expect($class::getCluster())->toBe(CatalogSettings::class)
        ->and($class::getNavigationGroup())->toBeNull();
})->with([
    'Format' => [FormatResource::class],
    'Case Configuration' => [CaseConfigurationResource::class],
    'Product Reference' => [ProductReferenceResource::class],
]);

it('places the Catalog Settings cluster itself inside the Catalog group', function (): void {
    expect(CatalogSettings::getNavigationGroup())->toBe(OperatorConsoleNavigationGroup::Catalog);
});

it('assigns each top-level Parties console to the Parties group, ordered and sidebar-visible', function (string $class, int $sort): void {
    /** @var class-string<OperatorConsoleResource> $class */
    expect($class::getNavigationGroup())->toBe(OperatorConsoleNavigationGroup::Parties)
        ->and($class::getNavigationSort())->toBe($sort)
        ->and($class::shouldRegisterNavigation())->toBeTrue();
})->with([
    'Customer' => [CustomerResource::class, 1],
    'Profile (Memberships)' => [ProfileResource::class, 2],
    'Producer' => [ProducerResource::class, 4],
    'Supplier' => [SupplierResource::class, 6],
]);

it('hides the nested child consoles from the sidebar (routes stay registered, only navigation is suppressed)', function (string $class): void {
    /** @var class-string<OperatorConsoleResource> $class */
    expect($class::shouldRegisterNavigation())->toBeFalse();
})->with([
    'Product Variant' => [ProductVariantResource::class],
    'Club' => [ClubResource::class],
    'Producer Agreement' => [ProducerAgreementResource::class],
]);

it('relabels the Profile console as "Memberships" in the sidebar, per operator locale', function (): void {
    App::setLocale('en');
    expect(ProfileResource::getNavigationLabel())->toBe('Memberships');

    App::setLocale('it');
    expect(ProfileResource::getNavigationLabel())->toBe('Iscrizioni');
});

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
    App::setLocale('en');
    expect(OperatorConsoleNavigationGroup::Catalog->getLabel())->toBe('Catalog')
        ->and(OperatorConsoleNavigationGroup::Parties->getLabel())->toBe('Parties');

    App::setLocale('it');
    expect(OperatorConsoleNavigationGroup::Catalog->getLabel())->toBe('Catalogo')
        ->and(OperatorConsoleNavigationGroup::Parties->getLabel())->toBe('Anagrafiche');
});

it('localizes the Settings cluster label per operator locale', function (): void {
    App::setLocale('en');
    expect(CatalogSettings::getNavigationLabel())->toBe('Settings');

    App::setLocale('it');
    expect(CatalogSettings::getNavigationLabel())->toBe('Impostazioni');
});
