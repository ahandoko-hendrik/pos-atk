<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('code')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('name')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('phone')
                    ->columnSpanFull(),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
