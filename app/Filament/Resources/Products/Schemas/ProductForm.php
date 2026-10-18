<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')->label('SKU')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('barcode')->label('Barcode')->maxLength(60)->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama barang')->required()->columnSpanFull(),
                Select::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload()->required(),
                TextInput::make('base_unit')->label('Satuan dasar')->required()->maxLength(20),
                TextInput::make('alt_unit')->label('Satuan alternatif')->maxLength(20),
                TextInput::make('alt_unit_qty')->label('Isi per satuan alternatif')->numeric()->minValue(0.001),
                TextInput::make('min_stock')->label('Stok minimum')->numeric()->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
                Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ]);
    }
}