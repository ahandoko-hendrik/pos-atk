<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Harga jual';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tier_id')
                ->label('Tingkat harga')
                ->relationship('tier', 'name')
                ->required(),
            Select::make('unit_kind')
                ->label('Satuan')
                ->options(['dasar' => 'Satuan dasar', 'alternatif' => 'Satuan alternatif'])
                ->default('dasar')
                ->required(),
            TextInput::make('price')
                ->label('Harga (Rp)')
                ->numeric()
                ->minValue(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([
                TextColumn::make('tier.name')->label('Tingkat harga'),
                TextColumn::make('unit_kind')->label('Satuan'),
                TextColumn::make('price')->label('Harga (Rp)')->numeric(decimalPlaces: 0),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}