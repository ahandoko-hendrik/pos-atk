<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Services\SaleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class Kasir extends Page
{
    protected string $view = 'filament.pages.kasir';

    public $search = '';
    public $kategori = '';
    public $cart = [];          // kunci: "idproduk-satuan"
    public $discount = 0;
    public $cash = 0;
    public $method = 'tunai';
    public $tierCode = 'ECERAN';
    public $customerId = '';
    public $useCredit = false;
    public $dueDate = '';
    public $openingCash = 0;
    public $countedCash = '';
    public $showClose = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    // ---------------- shift kasir ----------------
    public function shift(): ?object
    {
        return DB::table('cash_shifts')
            ->where('cashier_id', auth()->id())
            ->whereNull('closed_at')
            ->first();
    }

    public function openShift(): void
    {
        if ($this->shift()) {
            return;
        }
        DB::table('cash_shifts')->insert([
            'cashier_id' => auth()->id(),
            'location_id' => $this->tokoId(),
            'opening_cash' => max(0, (float) $this->openingCash),
        ]);
        $this->openingCash = 0;
        Notification::make()->title('Shift dibuka')->success()->send();
        $this->dispatch('fokus-barcode');
    }

    public function shiftSales(): float
    {
        $s = $this->shift();

        return $s
            ? (float) DB::table('sales')->where('shift_id', $s->id)->where('status', 'selesai')->sum('total')
            : 0.0;
    }

    public function toggleClose(): void
    {
        $this->showClose = ! $this->showClose;
        $this->countedCash = '';
    }

    public function closeShift(): void
    {
        $s = $this->shift();
        if (! $s) {
            return;
        }
        if ($this->countedCash === '' || $this->countedCash === null) {
            Notification::make()->title('Isi jumlah uang yang ada di laci')->warning()->send();

            return;
        }
        try {
            $selisih = (float) DB::selectOne(
                'select close_shift(?::bigint, ?::numeric) as selisih',
                [$s->id, (float) $this->countedCash]
            )->selisih;
        } catch (Throwable $e) {
            Notification::make()->title('Shift gagal ditutup')->body($this->friendly($e))->danger()->send();

            return;
        }
        $this->countedCash = '';
        $this->showClose = false;
        $teks = $selisih == 0.0
            ? 'Kas pas, tidak ada selisih.'
            : ($selisih < 0
                ? 'Uang KURANG Rp' . number_format(abs($selisih), 0, ',', '.')
                : 'Uang LEBIH Rp' . number_format($selisih, 0, ',', '.'));
        Notification::make()->title('Shift ditutup')->body($teks)->success()->persistent()->send();
    }

    // ---------------- katalog produk ----------------
    public function categories()
    {
        return DB::table('categories')->orderBy('name')->get(['id', 'name']);
    }

    public function cards(): Collection
    {
        $loc = $this->tokoId();
        $tierId = $this->tierId();
        $eceranId = (int) DB::table('price_tiers')->where('code', 'ECERAN')->value('id');
        $s = trim((string) $this->search);

        return DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('stock_balances as b', function ($j) use ($loc) {
                $j->on('b.product_id', '=', 'p.id')->where('b.location_id', '=', $loc);
            })
            ->leftJoin('product_prices as pt', function ($j) use ($tierId) {
                $j->on('pt.product_id', '=', 'p.id')->where('pt.unit_kind', '=', 'dasar')->where('pt.tier_id', '=', $tierId);
            })
            ->leftJoin('product_prices as pe', function ($j) use ($eceranId) {
                $j->on('pe.product_id', '=', 'p.id')->where('pe.unit_kind', '=', 'dasar')->where('pe.tier_id', '=', $eceranId);
            })
            ->where('p.is_active', true)
            ->when($this->kategori !== '', fn ($q) => $q->where('p.category_id', (int) $this->kategori))
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('p.name', 'ilike', '%' . $s . '%')
                ->orWhere('p.sku', 'ilike', '%' . $s . '%')
                ->orWhere('p.barcode', $s)))
            ->orderBy('p.name')
            ->limit(60)
            ->get([
                'p.id', 'p.sku', 'p.name', 'p.base_unit', 'p.alt_unit', 'p.min_stock',
                'c.name as kategori',
                DB::raw('coalesce(b.qty, 0) as stok'),
                DB::raw('coalesce(pt.price, pe.price) as harga'),
            ]);
    }

    // Enter di kolom cari: cocokkan barcode/SKU persis; bila tidak ada, ambil bila hasil pencarian tinggal satu
    public function scanOrAdd(): void
    {
        $code = trim((string) $this->search);
        if ($code === '') {
            return;
        }
        $p = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();
        if (! $p) {
            $list = $this->cards();
            if ($list->count() === 1) {
                $p = Product::find($list->first()->id);
            }
        }
        if (! $p) {
            Notification::make()->title('Barang tidak ditemukan')->warning()->send();

            return;
        }
        $this->addProduct($p->id);
        $this->search = '';
    }

    // ---------------- keranjang ----------------
    public function addProduct(int $id, string $unitKind = 'dasar'): void
    {
        $p = Product::findOrFail($id);
        $key = $id . '-' . $unitKind;

        if ($unitKind === 'alternatif' && ! $p->alt_unit_qty) {
            return;
        }
        $need = $unitKind === 'alternatif' ? (float) $p->alt_unit_qty : 1.0;
        $onCart = isset($this->cart[$key]) ? (float) $this->cart[$key]['qty'] : 0.0;
        $stock = (float) DB::table('stock_balances')
            ->where('product_id', $id)->where('location_id', $this->tokoId())->value('qty');
        $allowNeg = DB::table('app_settings')->where('key', 'allow_negative_stock')->value('value') === 'true';
        if (! $allowNeg && ($onCart + 1) * $need > $stock) {
            Notification::make()
                ->title('Stok ' . $p->name . ' tidak cukup')
                ->body('Stok tersedia ' . rtrim(rtrim(number_format($stock, 3, ',', '.'), '0'), ',') . ' ' . $p->base_unit)
                ->warning()->send();

            return;
        }

        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty']++;

            return;
        }
        $price = $this->priceFor($id, $unitKind);
        if ($price === null) {
            Notification::make()->title('Harga ' . $p->name . ' belum diisi')->warning()->send();

            return;
        }
        $this->cart[$key] = [
            'product_id' => $id,
            'unit_kind' => $unitKind,
            'name' => $p->name,
            'unit' => $unitKind === 'alternatif' ? $p->alt_unit : $p->base_unit,
            'qty' => 1,
            'unit_price' => $price,
            'discount' => 0,
        ];
    }

    public function removeLine(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function resetCart(): void
    {
        $this->reset(['cart', 'discount', 'cash', 'search', 'customerId', 'useCredit', 'dueDate']);
        $this->dispatch('fokus-barcode');
    }

    public function total(): float
    {
        $sub = collect($this->cart)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_price'] - (float) $l['discount']);

        return max(0, $sub - (float) $this->discount);
    }

    // harga menurut tingkat yang dipilih; bila belum diisi, memakai harga ECERAN
    private function priceFor(int $productId, string $unitKind): ?float
    {
        foreach ([$this->tierCode, 'ECERAN'] as $code) {
            $price = DB::table('product_prices as pp')
                ->join('price_tiers as t', 't.id', '=', 'pp.tier_id')
                ->where('pp.product_id', $productId)
                ->where('pp.unit_kind', $unitKind)
                ->where('t.code', $code)
                ->value('pp.price');
            if ($price !== null) {
                return (float) $price;
            }
        }

        return null;
    }

    private function tierId(): int
    {
        return (int) DB::table('price_tiers')->where('code', $this->tierCode)->value('id');
    }

    private function tokoId(): int
    {
        return (int) DB::table('locations')->where('code', 'TOKO')->value('id');
    }

    public function tiers()
    {
        return DB::table('price_tiers')->orderBy('id')->get(['code', 'name']);
    }

    public function customers()
    {
        return DB::table('customers')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function customer(): ?object
    {
        if (! $this->customerId) {
            return null;
        }

        return DB::table('customers')->where('id', (int) $this->customerId)->first();
    }

    public function outstanding(): float
    {
        if (! $this->customerId) {
            return 0.0;
        }

        return (float) DB::table('v_receivable')->where('customer_id', (int) $this->customerId)->sum('outstanding');
    }

    // ---------------- reaksi saat pilihan berubah ----------------
    public function updatedTierCode(): void
    {
        foreach ($this->cart as $key => $line) {
            $price = $this->priceFor($line['product_id'], $line['unit_kind']);
            if ($price !== null) {
                $this->cart[$key]['unit_price'] = $price;
            }
        }
    }

    public function updatedCustomerId(): void
    {
        $this->useCredit = false;
        $this->dueDate = '';
    }

    public function updatedUseCredit($value): void
    {
        if (! $value) {
            $this->dueDate = '';

            return;
        }
        $c = $this->customer();
        if (! $c || ! $c->allow_credit) {
            $this->useCredit = false;
            Notification::make()->title('Pelanggan ini tidak boleh tempo')->warning()->send();

            return;
        }
        $this->dueDate = now()->addDays((int) $c->credit_days)->toDateString();
    }

    // ---------------- bayar ----------------
    public function checkout(SaleService $sales): void
    {
        $shift = $this->shift();
        if (! $shift) {
            Notification::make()->title('Buka shift terlebih dahulu')->warning()->send();

            return;
        }
        if (! $this->cart) {
            return;
        }
        foreach ($this->cart as $l) {
            if ((float) $l['qty'] <= 0) {
                Notification::make()->title('Jumlah harus lebih dari 0')->warning()->send();

                return;
            }
        }

        $customerId = $this->customerId ? (int) $this->customerId : null;
        $total = $this->total();
        $paid = min((float) $this->cash, $total);

        $dueDate = null;
        if ($this->useCredit && $paid < $total) {
            if (! $customerId) {
                Notification::make()->title('Pilih pelanggan untuk penjualan tempo')->warning()->send();

                return;
            }
            if ($this->dueDate === '') {
                Notification::make()->title('Isi tanggal jatuh tempo')->warning()->send();

                return;
            }
            $dueDate = (string) $this->dueDate;
        }

        try {
            $saleId = $sales->checkout(
                items: array_values($this->cart),
                payments: [['method' => $this->method, 'amount' => $paid]],
                cashierId: auth()->id(),
                locationId: $this->tokoId(),
                tierId: $this->tierId(),
                customerId: $customerId,
                discount: (float) $this->discount,
                dueDate: $dueDate,
                shiftId: (int) $shift->id,
            );
        } catch (Throwable $e) {
            Notification::make()->title('Transaksi gagal')->body($this->friendly($e))->danger()->send();

            return;
        }
        $docNo = DB::table('sales')->where('id', $saleId)->value('doc_no');
        $this->reset(['cart', 'discount', 'cash', 'search', 'customerId', 'useCredit', 'dueDate', 'tierCode', 'method']);
        Notification::make()->title('Nota ' . $docNo . ' tersimpan')->success()->send();
        $this->dispatch('cetak-struk', url: route('struk', $saleId));
        $this->dispatch('fokus-barcode');
    }

    private function friendly(Throwable $e): string
    {
        // pesan dari database, mis. "Stok tidak cukup (...)" atau "Melebihi batas kredit ..."
        if (preg_match('/ERROR:\s+(.+?)\s+\(Connection:/s', $e->getMessage(), $m)) {
            return $m[1];
        }

        return $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan. Hubungi admin.';
    }
}