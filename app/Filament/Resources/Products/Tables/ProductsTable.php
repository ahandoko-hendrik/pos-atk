<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama barang')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Kategori')->sortable(),
                TextColumn::make('base_unit')->label('Satuan'),
                TextColumn::make('stok')
                    ->label('Stok')
                    ->state(fn($record) => (float) DB::table('stock_balances')->where('product_id', $record->id)->sum('qty')),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
