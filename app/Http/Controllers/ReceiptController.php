<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function show(int $id)
    {
        $sale = DB::table('sales')->where('id', $id)->first();
        abort_unless($sale, 404);

        $items = DB::table('sale_items as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.sale_id', $id)
            ->select('p.name', 'i.qty', 'i.unit_price', 'i.line_total')
            ->get();

        $payments = DB::table('payments as pay')
            ->join('payment_allocations as a', 'a.payment_id', '=', 'pay.id')
            ->where('a.sale_id', $id)
            ->select('pay.method', 'a.amount')
            ->get();

        return view('struk', compact('sale', 'items', 'payments'));
    }
}