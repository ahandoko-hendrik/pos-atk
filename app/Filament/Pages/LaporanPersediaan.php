<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanPersediaan extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.laporan-persediaan';

    public $dari = '';
    public $sampai = '';
    public $lokasi = '';

    public function mount(): void
    {
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
    }

    public function locations()
    {
        return DB::table('locations')->orderBy('id')->get(['id', 'name']);
    }

    public function rows(): array
    {
        if (! $this->dari || ! $this->sampai) {
            return [];
        }

        return DB::select(
            'select * from report_persediaan(?::date, ?::date, ?::integer)',
            [$this->dari, $this->sampai, $this->lokasi !== '' ? (int) $this->lokasi : null]
        );
    }

    public function totals(array $rows): array
    {
        $t = ['awal' => 0.0, 'beli' => 0.0, 'jual' => 0.0, 'lain' => 0.0, 'akhir' => 0.0];
        foreach ($rows as $r) {
            $t['awal'] += (float) $r->awal_nilai;
            $t['beli'] += (float) $r->pembelian_nilai;
            $t['jual'] += (float) $r->penjualan_hpp;
            $t['lain'] += (float) $r->lain_nilai;
            $t['akhir'] += (float) $r->akhir_nilai;
        }

        return $t;
    }

    public function unduh(): StreamedResponse
    {
        $rows = $this->rows();
        $data = [[
            'SKU', 'Nama Barang', 'Kategori', 'Satuan',
            'Awal (qty)', 'Awal (Rp)', 'Pembelian (qty)', 'Pembelian (Rp)',
            'Penjualan (qty)', 'Penjualan HPP (Rp)', 'Lain-lain (qty)', 'Lain-lain (Rp)',
            'Akhir (qty)', 'Akhir (Rp)',
        ]];
        foreach ($rows as $r) {
            $data[] = [
                $r->sku, $r->nama, $r->kategori, $r->satuan,
                (float) $r->awal_qty, (float) $r->awal_nilai,
                (float) $r->pembelian_qty, (float) $r->pembelian_nilai,
                (float) $r->penjualan_qty, (float) $r->penjualan_hpp,
                (float) $r->lain_qty, (float) $r->lain_nilai,
                (float) $r->akhir_qty, (float) $r->akhir_nilai,
            ];
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Persediaan');
        $sheet->fromArray($data, null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('C2');

        $nama = 'laporan_persediaan_' . $this->dari . '_sd_' . $this->sampai . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $nama);
    }
}