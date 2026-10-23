<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Services\SaleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class Kasir extends Page
{
    protected string $view = 'filament.pages.kasir';

    public $barcode = '';       // properti input dibiarkan tanpa tipe agar isian kosong tidak error
    public $search = '';
    public $cart = [];          // kunci: "idproduk-satuan"
    public $discount = 0;
    public $cash = 0;

    public function addByBarcode(): void
    {
        $code = trim((string) $this->barcode);
        $this->barcode = '';
        if ($code === '') {
            return;
        }
        $p = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();
        if ($p) {
            $this->addProduct($p->id);
        } else {
            Notification::make()->title('Barang tidak ditemukan')->warning()->send();
        }
    }

    public function addProduct(int $id, string $unitKind = 'dasar'): void
    {
        $p = Product::findOrFail($id);
        $key = $id . '-' . $unitKind;
        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty']++;

            return;
        }
        $price = DB::table('product_prices')
            ->where('product_id', $id)->where('unit_kind', $unitKind)
            ->where('tier_id', $this->tierId())->value('price');
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
            'unit_price' => (float) $price,
            'discount' => 0,
        ];
    }

    public function removeLine(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function results()
    {
        if (strlen(trim((string) $this->search)) < 2) {
            return collect();
        }

        return Product::where('is_active', true)
            ->where('name', 'ilike', '%' . trim($this->search) . '%')
            ->orderBy('name')->limit(8)->get();
    }

    public function total(): float
    {
        $sub = collect($this->cart)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_price'] - (float) $l['discount']);

        return max(0, $sub - (float) $this->discount);
    }

    private function tierId(): int
    {
        return (int) DB::table('price_tiers')->where('code', 'ECERAN')->value('id');
    }

    public function checkout(SaleService $sales): void
    {
        foreach ($this->cart as $l) {
            if ((float) $l['qty'] <= 0) {
                Notification::make()->title('Jumlah harus lebih dari 0')->warning()->send();

                return;
            }
        }
        try {
            $saleId = $sales->checkout(
                items: array_values($this->cart),
                payments: [['method' => 'tunai', 'amount' => min((float) $this->cash, $this->total())]],
                cashierId: auth()->id(),
                locationId: (int) DB::table('locations')->where('code', 'TOKO')->value('id'),
                tierId: $this->tierId(),
                discount: (float) $this->discount,
            );
        } catch (Throwable $e) {
            Notification::make()->title('Transaksi gagal')->body($this->friendly($e))->danger()->send();

            return;
        }
        $docNo = DB::table('sales')->where('id', $saleId)->value('doc_no');
        $this->reset(['cart', 'discount', 'cash', 'barcode', 'search']);
        Notification::make()->title('Nota ' . $docNo . ' tersimpan')->success()->send();
        $this->dispatch('cetak-struk', url: route('struk', $saleId));
    }

    private function friendly(Throwable $e): string
    {
        // pesan dari database, mis. "Stok tidak cukup (...)"
        if (preg_match('/ERROR:\s+(.+?)\s+\(Connection:/s', $e->getMessage(), $m)) {
            return $m[1];
        }

        return $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan. Hubungi admin.';
    }
}