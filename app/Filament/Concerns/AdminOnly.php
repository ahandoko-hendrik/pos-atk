<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Menyembunyikan dan memblokir halaman atau resource bagi akun selain admin dan pemilik.
 */
trait AdminOnly
{
    protected static function isAdminUser(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canAccess(): bool
    {
        return static::isAdminUser();
    }

    public static function canViewAny(): bool
    {
        return static::isAdminUser();
    }

    public static function canCreate(): bool
    {
        return static::isAdminUser();
    }

    public static function canView(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function canDelete(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function canDeleteAny(): bool
    {
        return static::isAdminUser();
    }
}