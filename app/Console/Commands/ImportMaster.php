<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ImportMaster extends Command
{
    protected $signature = 'pos:import {file} {--user=1 : id pengguna pencatat saldo awal}';
    protected $description = 'Impor master produk dan saldo awal stok dari file Excel';

    public function handle(): int
    {
        if (DB::table('products')->exists()) {
            $this->error('Tabel products sudah berisi data. Impor dibatalkan.');
            return self::FAILURE;
        }

        $book = IOFactory::load($this->argument('file'));
        $master = $book->getSheetByName('Master Produk')->toArray(null, true, false, false);
        $saldo = $book->getSheetByName('Saldo Awal')->toArray(null, true, false, false);

        $cats = DB::table('categories')->pluck('id', 'name');
        $locs = DB::table('locations')->pluck('id', 'code');
        $tier = DB::table('price_tiers')->where('code', 'ECERAN')->value('id');
        $user = (int) $this->option('user');

        DB::transaction(function () use ($master, $saldo, $cats, $locs, $tier, $user) {
            $ids = [];
            foreach (array_slice($master, 1) as $r) {
                if (empty($r[0])) {
                    continue;
                }
                $catId = $cats[$r[2]] ?? throw new RuntimeException('Kategori tidak dikenal: ' . $r[2]);
                $id = DB::table('products')->insertGetId([
                    'sku' => $r[0],
                    'name' => $r[1],
                    'category_id' => $catId,
                    'base_unit' => $r[3],
                    'alt_unit' => $r[4] ?: null,
                    'alt_unit_qty' => $r[4] ? $r[5] : null,
                    'barcode' => $r[6] ?: null,
                    'min_stock' => $r[12] ?: 0,
                ], 'id');
                $ids[$r[0]] = $id;

                DB::table('product_prices')->insert([
                    'product_id' => $id, 'tier_id' => $tier, 'unit_kind' => 'dasar', 'price' => $r[8],
                ]);
                if ($r[4] && $r[9]) {
                    DB::table('product_prices')->insert([
                        'product_id' => $id, 'tier_id' => $tier, 'unit_kind' => 'alternatif', 'price' => $r[9],
                    ]);
                }
            }

            foreach (array_slice($saldo, 1) as $s) {
                if (! isset($ids[$s[0]]) || (float) $s[3] <= 0) {
                    continue;   // lewati baris TOTAL dan stok nol
                }
                DB::select("select apply_stock_movement(?::bigint, ?::integer, 'OPENING', ?::numeric, ?::numeric, ?::bigint, 'opening', null, 'Saldo awal 30 Sep 2026', '2026-09-30 23:59:00+07'::timestamptz)", [
                    $ids[$s[0]], $locs[$s[2]], $s[3], $s[4], $user,
                ]);
            }
            $this->info('Berhasil mengimpor ' . count($ids) . ' produk.');
        });

        return self::SUCCESS;
    }
}