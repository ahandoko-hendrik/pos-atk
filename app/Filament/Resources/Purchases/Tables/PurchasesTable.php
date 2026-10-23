<?php

namespace App\Filament\Resources\Purchases\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('doc_no')->label('No. dokumen')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Pemasok'),
                TextColumn::make('purchase_date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('total')->label('Total (Rp)')->numeric(decimalPlaces: 0),
            ])
            ->defaultSort('id', 'desc');
    }
}