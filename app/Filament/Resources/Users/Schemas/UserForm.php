<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('Email (dipakai sebagai nama login)')
                    ->required()
                    ->email()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('email', strtolower(trim((string) $state))))
                    ->helperText('Boleh alamat fiktif, mis. kasir1@plmaju.local'),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Kosongkan bila tidak ingin mengganti kata sandi.'
                        : 'Minimal 8 karakter.'),
                Select::make('role')
                    ->label('Peran')
                    ->options([
                        'kasir' => 'Kasir (hanya Kasir dan Piutang)',
                        'admin' => 'Admin (semua menu)',
                        'pemilik' => 'Pemilik (semua menu, kecuali Pengguna)',
                    ])
                    ->default('kasir')
                    ->required()
                    ->disabled(fn (?Model $record): bool => $record?->id === auth()->id())
                    ->helperText('Peran akun Anda sendiri tidak bisa diubah di sini.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->disabled(fn (?Model $record): bool => $record?->id === auth()->id())
                    ->helperText('Matikan untuk melarang login tanpa menghapus riwayat transaksinya.'),
            ]);
    }
}