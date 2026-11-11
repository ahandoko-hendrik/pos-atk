<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email / login')->searchable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst((string) $state))
                    ->color(fn ($state): string => match ($state) {
                        'admin' => 'danger',
                        'pemilik' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),

                Action::make('toggle')
                    ->label(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon('heroicon-o-power')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id())
                    ->action(function (User $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Pengguna diaktifkan' : 'Pengguna dinonaktifkan')
                            ->success()->send();
                    }),

                Action::make('hapus')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus pengguna?')
                    ->modalDescription('Pengguna yang sudah punya riwayat transaksi tidak bisa dihapus. Untuk mereka, gunakan Nonaktifkan.')
                    ->hidden(fn (User $record): bool => $record->id === auth()->id())
                    ->action(function (User $record): void {
                        if (self::hasHistory($record->id)) {
                            Notification::make()
                                ->title('Pengguna tidak bisa dihapus')
                                ->body('Pengguna ini sudah punya riwayat (nota, shift, pembelian, atau opname). Gunakan Nonaktifkan.')
                                ->danger()->send();

                            return;
                        }
                        $record->delete();
                        Notification::make()->title('Pengguna dihapus')->success()->send();
                    }),
            ]);
    }

    private static function hasHistory(int $id): bool
    {
        $refs = [
            ['sales', 'cashier_id'], ['sales', 'voided_by'], ['payments', 'user_id'],
            ['purchases', 'created_by'], ['cash_shifts', 'cashier_id'],
            ['stock_movements', 'user_id'], ['stock_opnames', 'created_by'], ['audit_log', 'user_id'],
        ];
        foreach ($refs as [$tabel, $kolom]) {
            if (DB::table($tabel)->where($kolom, $id)->exists()) {
                return true;
            }
        }

        return false;
    }
}