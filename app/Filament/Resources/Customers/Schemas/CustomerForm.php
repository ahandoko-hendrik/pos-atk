<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
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
                Textarea::make('customer_type')
                    ->required()
                    ->default('umum')
                    ->columnSpanFull(),
                Textarea::make('phone')
                    ->columnSpanFull(),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('allow_credit')
                    ->required(),
                TextInput::make('credit_limit')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('credit_days')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
