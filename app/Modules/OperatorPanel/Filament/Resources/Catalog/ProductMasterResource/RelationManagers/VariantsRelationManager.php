<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\RelationManagers;

use App\Modules\Catalog\Actions\CreateProductVariant as CreateProductVariantAction;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\CreateProductVariant;
use App\Platform\I18n\TranslatableText;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * VariantsRelationManager — a Product Master's child Product Variants, surfaced as an interactive sub-table on
 * the Master's view page (operator-console UI pass, 2026-06-24). It replaces the standalone Product Variant
 * sidebar console (now hidden from navigation): an operator sees AND creates a Master's Variants in the
 * Master's own context, with the parent Master implied (no Master picker on the create form).
 *
 * Read columns are reused verbatim from {@see ProductVariantResource::table()} (the same kit-rendered lifecycle
 * badge); the row View action links to the still-registered Variant view page. Create routes through the Catalog
 * {@see CreateProductVariantAction} with the owner Master id injected — NEVER an Eloquent write (the
 * no-Eloquent-write rule; ADR 2026-06-19) — mirroring the standalone CreateProductVariant page's payload
 * narrowing, minus the parent picker. All copy is localized (invariant 12).
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('operator_console.relations.variants');
    }

    /**
     * Authorize create at the console boundary: an authenticated operator may create, with the Catalog domain
     * action as the real business-rule guard (there is no per-model Eloquent policy in this app, so the RM's
     * create action is explicitly enabled — mirroring the standalone write-through create page being reachable).
     */
    protected function canCreate(): bool
    {
        return true;
    }

    /**
     * Opt OUT of Filament's default "a relation manager is read-only on a ViewRecord page" rule
     * (RelationManager::isReadOnly() === is_subclass_of(pageClass, ViewRecord::class)). The operator console's
     * parent pages are ViewRecords (read-projection), so without this the header CreateAction is DENIED before
     * {@see canCreate()} is ever consulted (RelationManager action authorization: CreateAction => isReadOnly ?
     * deny) — which is exactly why the "New variant" button did not appear on the Master view. We surface it; the
     * Catalog CreateProductVariant domain action stays the real write-through guard, and no edit/delete actions
     * are defined, so this enables the create affordance ONLY.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return ProductVariantResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->label((string) __('operator_console.relations.create_variant'))
                    ->modalHeading((string) __('operator_console.relations.create_variant'))
                    ->schema([
                        TextInput::make('variant_identifier')
                            ->label((string) __('operator_console.product_variant.fields.variant_identifier'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('vintage_year')
                            ->label((string) __('operator_console.product_variant.fields.vintage_year'))
                            ->numeric(),
                        Toggle::make('non_vintage')
                            ->label((string) __('operator_console.product_variant.fields.non_vintage'))
                            ->default(false),
                        Textarea::make('tasting_notes')
                            ->label((string) __('operator_console.product_variant.fields.description'))
                            ->helperText((string) __('operator_console.product_variant.fields.tasting_notes_help')),
                    ])
                    ->using($this->createVariant(...)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ProductVariantResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * Route the inline create through the Catalog domain action with the owner Master id injected — the parent is
     * the relation owner, never a form input. Mirrors {@see CreateProductVariant::createViaAction()}
     * payload narrowing; the write happens INSIDE the action (the console performs no Eloquent write).
     *
     * @param  array<string, mixed>  $data
     */
    private function createVariant(array $data): Model
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof ProductMaster);

        $variantIdentifier = $data['variant_identifier'] ?? null;
        $vintageYear = $data['vintage_year'] ?? null;
        $tastingNotes = $data['tasting_notes'] ?? null;

        if (
            ! is_string($variantIdentifier)
            || ! (is_null($vintageYear) || $vintageYear === '' || is_numeric($vintageYear))
            || ! (is_null($tastingNotes) || is_string($tastingNotes))
        ) {
            throw new InvalidArgumentException('Unexpected Product Variant create payload.');
        }

        return app(CreateProductVariantAction::class)->handle(
            productMasterId: $owner->id,
            variantIdentifier: $variantIdentifier,
            vintageYear: ($vintageYear === null || $vintageYear === '') ? null : (int) $vintageYear,
            nonVintage: (bool) ($data['non_vintage'] ?? false),
            tastingNotes: ($tastingNotes === null || $tastingNotes === '')
                ? null
                : TranslatableText::of(['en' => $tastingNotes]),
        );
    }
}
