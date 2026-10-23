<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Location;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')->label('Pemasok')->relationship('supplier', 'name')->searchable()->preload(),
                DatePicker::make('purchase_date')->label('Tanggal')->default(now())->required(),
                TextInput::make('supplier_invoice_no')->label('No. faktur pemasok'),
                Select::make('location_id')->label('Lokasi')
                    ->options(fn () => Location::orderBy('id')->pluck('name', 'id')->all())
                    ->default(fn () => Location::where('code', 'TOKO')->value('id'))
                    ->required(),
                Repeater::make('items')
                    ->label('Barang dibeli')
                    ->schema([
                        Select::make('product_id')->label('Barang')->searchable()->required()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where('is_active', true)
                                ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                                    ->orWhere('sku', 'ilike', "%{$search}%")
                                    ->orWhere('barcode', $search))
                                ->orderBy('name')->limit(30)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name),
                        Select::make('unit_kind')->label('Satuan')
                            ->options(['dasar' => 'Satuan dasar', 'alternatif' => 'Satuan alternatif (box)'])
                            ->default('dasar')->required(),
                        TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required(),
                        TextInput::make('unit_price')->label('Harga per satuan (Rp)')->numeric()->minValue(0)->required(),
                    ])
                    ->columns(4)
                    ->minItems(1)
                    ->addActionLabel('Tambah barang')
                    ->columnSpanFull(),
                Textarea::make('note')->label('Catatan')->columnSpanFull(),
            ]);
    }
}