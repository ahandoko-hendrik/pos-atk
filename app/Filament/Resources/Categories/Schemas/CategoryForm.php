<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CategoryForm
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
            ]);
    }
}
