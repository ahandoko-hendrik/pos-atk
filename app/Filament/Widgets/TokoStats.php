<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TokoStats extends StatsOverviewWidget
{
        public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
    protected function getStats(): array
    {
        $hariIni = now()->toDateString();
        $omzet = DB::table('v_sales_daily')->where('tanggal', $hariIni)->sum('omzet');
        $laba = DB::table('v_sales_daily')->where('tanggal', $hariIni)->sum('laba_kotor');
        $nota = DB::table('v_sales_daily')->where('tanggal', $hariIni)->sum('jumlah_nota');
        $piutang = DB::table('v_ar_aging')->sum('total_piutang');
        $menipis = DB::table('v_stock_current')
            ->where(fn ($q) => $q->where('is_low', true)->orWhere('is_empty', true))
            ->count();

        return [
            Stat::make('Omzet hari ini', 'Rp' . number_format($omzet, 0, ',', '.'))
                ->description($nota . ' nota'),
            Stat::make('Laba kotor hari ini', 'Rp' . number_format($laba, 0, ',', '.')),
            Stat::make('Total piutang', 'Rp' . number_format($piutang, 0, ',', '.')),
            Stat::make('Stok menipis atau habis', $menipis),
        ];
    }
}