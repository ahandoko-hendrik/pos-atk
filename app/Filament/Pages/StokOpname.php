<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StokOpname extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.stok-opname';

    public $lokasi = '';
    public $kategori = '';
    public $search = '';
    public $counts = [];    // id produk => hasil hitung fisik
    public $note = '';

    public function mount(): void
    {
        $this->lokasi = (string) DB::table('locations')->where('code', 'TOKO')->value('id');
    }

    public function updatedLokasi(): void
    {
        $this->counts = [];
    }

    public function locations()
    {
        return DB::table('locations')->orderBy('id')->get(['id', 'name']);
    }

    public function categories()
    {
        return DB::table('categories')->orderBy('name')->get(['id', 'name']);
    }

    public function products()
    {
        $loc = (int) $this->lokasi;
        $s = trim((string) $this->search);

        return DB::table('products as p')
            ->leftJoin('stock_balances as b', function ($j) use ($loc) {
                $j->on('b.product_id', '=', 'p.id')->where('b.location_id', '=', $loc);
            })
            ->where('p.is_active', true)
            ->when($this->kategori !== '', fn ($q) => $q->where('p.category_id', (int) $this->kategori))
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('p.name', 'ilike', '%' . $s . '%')
                ->orWhere('p.sku', 'ilike', '%' . $s . '%')))
            ->orderBy('p.name')
            ->limit(200)
            ->get(['p.id', 'p.sku', 'p.name', 'p.base_unit', DB::raw('coalesce(b.qty, 0) as qty')]);
    }

    public function filled()
    {
        return collect($this->counts)->filter(fn ($v) => $v !== '' && $v !== null);
    }

    public function resetCounts(): void
    {
        $this->counts = [];
    }

    public function recent()
    {
        return DB::table('stock_opnames as o')
            ->leftJoin('stock_opname_items as i', 'i.opname_id', '=', 'o.id')
            ->groupBy('o.id', 'o.doc_no', 'o.opname_date', 'o.status')
            ->orderByDesc('o.id')
            ->limit(8)
            ->selectRaw('o.doc_no, o.opname_date, o.status, count(i.id) as item, count(i.id) filter (where i.diff_qty <> 0) as selisih')
            ->get();
    }

    public function selesaikan(): void
    {
        $filled = $this->filled();
        if ($filled->isEmpty()) {
            Notification::make()->title('Belum ada hasil hitung yang diisi')->warning()->send();

            return;
        }
        foreach ($filled as $v) {
            if (! is_numeric($v) || (float) $v < 0) {
                Notification::make()->title('Hasil hitung harus berupa angka 0 atau lebih')->warning()->send();

                return;
            }
        }

        $loc = (int) $this->lokasi;
        $note = trim((string) $this->note);

        try {
            $docNo = DB::transaction(function () use ($filled, $loc, $note) {
                $docNo = DB::selectOne("select next_doc_no('SO', ?::date) as no", [now()->toDateString()])->no;
                $opnameId = DB::table('stock_opnames')->insertGetId([
                    'doc_no' => $docNo,
                    'location_id' => $loc,
                    'opname_date' => now()->toDateString(),
                    'created_by' => auth()->id(),
                    'note' => $note !== '' ? $note : null,
                ], 'id');

                $ids = $filled->keys()->map(fn ($k) => (int) $k)->sort()->values();
                foreach ($ids as $id) {
                    $bal = DB::table('stock_balances')
                        ->where('product_id', $id)->where('location_id', $loc)
                        ->lockForUpdate()->first();
                    $system = $bal ? (float) $bal->qty : 0.0;
                    $avg = $bal ? (float) $bal->avg_cost : 0.0;
                    $counted = (float) $filled->get($id);

                    if ($counted > $system && $avg <= 0) {
                        $name = DB::table('products')->where('id', $id)->value('name');
                        throw new RuntimeException($name . ' belum punya harga pokok. Catat barang masuknya lewat menu Pembelian, bukan opname.');
                    }
                    DB::table('stock_opname_items')->insert([
                        'opname_id' => $opnameId,
                        'product_id' => $id,
                        'system_qty' => $system,
                        'counted_qty' => $counted,
                    ]);
                }

                // selisih dibukukan database sebagai penyesuaian stok
                DB::select('select post_opname(?::bigint, ?::bigint)', [$opnameId, auth()->id()]);

                return $docNo;
            });
        } catch (Throwable $e) {
            Notification::make()->title('Opname gagal')->body($this->friendly($e))->danger()->send();

            return;
        }

        $this->counts = [];
        $this->note = '';
        Notification::make()->title('Opname ' . $docNo . ' selesai')->success()->send();
    }

    private function friendly(Throwable $e): string
    {
        if (preg_match('/ERROR:\s+(.+?)\s+\(Connection:/s', $e->getMessage(), $m)) {
            return $m[1];
        }

        return $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan. Hubungi admin.';
    }
}