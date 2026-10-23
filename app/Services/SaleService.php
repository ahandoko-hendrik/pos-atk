<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleService
{
    /**
     * $items:    [['product_id' => 1, 'unit_kind' => 'dasar', 'qty' => 2, 'unit_price' => 2000, 'discount' => 0], ...]
     * $payments: [['method' => 'tunai', 'amount' => 50000], ...]
     *            amount = jumlah yang dipakai untuk nota ini (kembalian tidak dicatat).
     */
    public function checkout(
        array $items,
        array $payments,
        int $cashierId,
        int $locationId,
        int $tierId,
        ?int $customerId = null,
        float $discount = 0,
        ?string $dueDate = null,
        ?int $shiftId = null,
    ): int {
        if (! $items) {
            throw new RuntimeException('Keranjang kosong');
        }

        return DB::transaction(function () use ($items, $payments, $cashierId, $locationId, $tierId, $customerId, $discount, $dueDate, $shiftId) {
            $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');

            $rows = [];
            $subtotal = 0.0;
            foreach ($items as $it) {
                $p = $products[$it['product_id']] ?? throw new RuntimeException('Produk tidak ditemukan');
                $isAlt = ($it['unit_kind'] ?? 'dasar') === 'alternatif';
                if ($isAlt && ! $p->alt_unit_qty) {
                    throw new RuntimeException($p->name . ' tidak punya satuan alternatif');
                }
                $qty = (float) $it['qty'];
                $price = (float) $it['unit_price'];
                $disc = (float) ($it['discount'] ?? 0);
                $subtotal += round($qty * $price - $disc, 2);
                $rows[] = [
                    'product_id' => $p->id,
                    'unit_kind' => $isAlt ? 'alternatif' : 'dasar',
                    'qty' => $qty,
                    'qty_base' => $isAlt ? $qty * (float) $p->alt_unit_qty : $qty,
                    'unit_price' => $price,
                    'discount' => $disc,
                ];
            }

            if ($discount > $subtotal) {
                throw new RuntimeException('Diskon melebihi subtotal');
            }
            $total = round($subtotal - $discount, 2);
            $paid = round((float) collect($payments)->sum('amount'), 2);
            if ($paid > $total) {
                throw new RuntimeException('Pembayaran melebihi total nota');
            }
            if ($paid < $total && ! $dueDate) {
                throw new RuntimeException('Uang diterima kurang dari total nota');
            }

            $today = now()->toDateString();
            $docNo = DB::selectOne("select next_doc_no('ATK', ?::date) as no", [$today])->no;

            $saleId = DB::table('sales')->insertGetId([
                'doc_no' => $docNo,
                'sale_date' => now(),
                'location_id' => $locationId,
                'tier_id' => $tierId,
                'customer_id' => $customerId,
                'cashier_id' => $cashierId,
                'shift_id' => $shiftId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'due_date' => $dueDate,
            ], 'id');

            DB::table('sale_items')->insert(array_map(fn ($r) => $r + ['sale_id' => $saleId], $rows));

            // memotong stok dan mencatat harga pokok: dilakukan database
            DB::select('select post_sale_stock(?::bigint, ?::bigint)', [$saleId, $cashierId]);

            foreach ($payments as $pay) {
                $amount = (float) $pay['amount'];
                if ($amount <= 0) {
                    continue;
                }
                $kwt = DB::selectOne("select next_doc_no('KWT', ?::date) as no", [$today])->no;
                $paymentId = DB::table('payments')->insertGetId([
                    'doc_no' => $kwt,
                    'customer_id' => $customerId,
                    'method' => $pay['method'],
                    'amount' => $amount,
                    'shift_id' => $shiftId,
                    'user_id' => $cashierId,
                    'reference' => $pay['reference'] ?? null,
                ], 'id');
                DB::table('payment_allocations')->insert([
                    'payment_id' => $paymentId, 'sale_id' => $saleId, 'amount' => $amount,
                ]);
            }

            return $saleId;
        });
    }
}