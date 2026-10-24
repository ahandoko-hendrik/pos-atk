<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    /**
     * Menerima pembayaran dari pelanggan dan mengalokasikannya ke nota tertua lebih dulu.
     * Boleh mencicil, boleh melunasi beberapa nota sekaligus.
     */
    public function receive(int $customerId, float $amount, string $method, int $userId, ?int $shiftId = null, ?string $reference = null): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah pembayaran harus lebih dari 0');
        }

        return DB::transaction(function () use ($customerId, $amount, $method, $userId, $shiftId, $reference) {
            $open = DB::table('v_receivable')
                ->where('customer_id', $customerId)
                ->orderBy('eff_due_date')->orderBy('sale_id')
                ->get();
            if ($open->isEmpty()) {
                throw new RuntimeException('Pelanggan ini tidak punya piutang');
            }

            $docNo = DB::selectOne("select next_doc_no('KWT', ?::date) as no", [now()->toDateString()])->no;
            $paymentId = DB::table('payments')->insertGetId([
                'doc_no' => $docNo,
                'customer_id' => $customerId,
                'method' => $method,
                'amount' => $amount,
                'shift_id' => $shiftId,
                'user_id' => $userId,
                'reference' => $reference,
            ], 'id');

            $left = round($amount, 2);
            foreach ($open as $inv) {
                if ($left <= 0) {
                    break;
                }
                $pay = min($left, (float) $inv->outstanding);
                DB::table('payment_allocations')->insert([
                    'payment_id' => $paymentId,
                    'sale_id' => $inv->sale_id,
                    'amount' => $pay,
                ]);
                $left = round($left - $pay, 2);
            }
            if ($left > 0) {
                throw new RuntimeException('Pembayaran melebihi total piutang (kelebihan Rp' . number_format($left, 0, ',', '.') . ')');
            }

            return $paymentId;
        });
    }
}