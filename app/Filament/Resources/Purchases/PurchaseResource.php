<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Concerns\AdminOnly;

use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'doc_no';

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
        ];
    }
}
