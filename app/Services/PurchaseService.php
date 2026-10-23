<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    public function create(array $data, int $userId): int
    {
        if (empty($data['items'])) {
            throw new RuntimeException('Pembelian harus berisi minimal satu barang');
        }

        return DB::transaction(function () use ($data, $userId) {
            $date = $data['purchase_date'];
            $docNo = DB::selectOne("select next_doc_no('PB', ?::date) as no", [$date])->no;

            $purchaseId = DB::table('purchases')->insertGetId([
                'doc_no' => $docNo,
                'supplier_id' => $data['supplier_id'] ?? null,
                'location_id' => $data['location_id'],
                'purchase_date' => $date,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'created_by' => $userId,
                'note' => $data['note'] ?? null,
            ], 'id');

            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
            foreach ($data['items'] as $it) {
                $p = $products[$it['product_id']];
                $isAlt = ($it['unit_kind'] ?? 'dasar') === 'alternatif';
                $factor = $isAlt ? (float) $p->alt_unit_qty : 1.0;
                if ($factor <= 0) {
                    throw new RuntimeException($p->name . ' tidak punya satuan alternatif');
                }
                $qty = (float) $it['qty'];
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'product_id' => $p->id,
                    'unit_kind' => $isAlt ? 'alternatif' : 'dasar',
                    'qty' => $qty,
                    'qty_base' => $qty * $factor,
                    'unit_cost_base' => round((float) $it['unit_price'] / $factor, 4),
                ]);
            }

            DB::select('select post_purchase_stock(?::bigint, ?::bigint)', [$purchaseId, $userId]);

            return $purchaseId;
        });
    }
}