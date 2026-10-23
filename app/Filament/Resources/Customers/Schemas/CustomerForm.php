<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true)
                    ->helperText('Samakan dengan kode di aplikasi percetakan, mis. CUST-01'),
                TextInput::make('name')->label('Nama')->required(),
                Select::make('customer_type')->label('Tipe')
                    ->options(['umum' => 'Umum', 'instansi' => 'Instansi'])->default('umum')->required(),
                TextInput::make('phone')->label('Telepon'),
                Textarea::make('address')->label('Alamat')->columnSpanFull(),
                Toggle::make('allow_credit')->label('Boleh tempo (bayar belakangan)')->default(false),
                TextInput::make('credit_limit')->label('Batas kredit (Rp, 0 = tanpa batas)')->numeric()->minValue(0)->default(0),
                TextInput::make('credit_days')->label('Jatuh tempo (hari)')->numeric()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
                Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ]);
    }
}