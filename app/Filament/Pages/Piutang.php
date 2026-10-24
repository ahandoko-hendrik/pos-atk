<?php

namespace App\Filament\Pages;

use App\Services\PaymentService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class Piutang extends Page
{
    protected string $view = 'filament.pages.piutang';

    public $customerId = '';
    public $amount = '';
    public $method = 'tunai';
    public $reference = '';

    public function agingRows()
    {
        return DB::table('v_ar_aging')->orderByDesc('total_piutang')->get();
    }

    public function invoices()
    {
        if (! $this->customerId) {
            return collect();
        }

        return DB::table('v_receivable')
            ->where('customer_id', (int) $this->customerId)
            ->orderBy('eff_due_date')->orderBy('sale_id')
            ->get();
    }

    public function totalOutstanding(): float
    {
        return (float) $this->invoices()->sum('outstanding');
    }

    public function customerName(): ?string
    {
        return $this->customerId
            ? DB::table('customers')->where('id', (int) $this->customerId)->value('name')
            : null;
    }

    public function recentPayments()
    {
        if (! $this->customerId) {
            return collect();
        }

        return DB::table('payments')
            ->where('customer_id', (int) $this->customerId)
            ->orderByDesc('id')->limit(10)
            ->get();
    }

    public function pilih(int $id): void
    {
        $this->customerId = $id;
        $this->amount = '';
        $this->reference = '';
    }

    public function lunasi(): void
    {
        $this->amount = $this->totalOutstanding();
    }

    public function terima(PaymentService $svc): void
    {
        if (! $this->customerId) {
            Notification::make()->title('Pilih pelanggan dulu')->warning()->send();

            return;
        }
        $amount = (float) $this->amount;
        if ($amount <= 0) {
            Notification::make()->title('Isi jumlah pembayaran')->warning()->send();

            return;
        }

        $shift = DB::table('cash_shifts')
            ->where('cashier_id', auth()->id())
            ->whereNull('closed_at')
            ->first();
        if ($this->method === 'tunai' && ! $shift) {
            Notification::make()->title('Buka shift di menu Kasir dulu agar uang tunai tercatat di kas')->warning()->send();

            return;
        }

        try {
            $paymentId = $svc->receive(
                (int) $this->customerId,
                $amount,
                (string) $this->method,
                auth()->id(),
                $shift?->id,
                $this->reference !== '' ? (string) $this->reference : null
            );
        } catch (Throwable $e) {
            Notification::make()->title('Pembayaran gagal')->body($this->friendly($e))->danger()->send();

            return;
        }

        $docNo = DB::table('payments')->where('id', $paymentId)->value('doc_no');
        $this->reset(['amount', 'reference']);
        Notification::make()->title('Pembayaran ' . $docNo . ' diterima')->success()->send();
    }

    public function batalkan(int $paymentId): void
    {
        if (! auth()->user()?->isAdmin()) {
            Notification::make()->title('Hanya admin yang boleh membatalkan pembayaran')->danger()->send();

            return;
        }
        $p = DB::table('payments')->where('id', $paymentId)->first();
        if (! $p || $p->status === 'void') {
            return;
        }

        DB::transaction(function () use ($p) {
            // database menghitung ulang sisa piutang otomatis
            DB::table('payments')->where('id', $p->id)->update(['status' => 'void']);
            DB::table('audit_log')->insert([
                'user_id' => auth()->id(),
                'action' => 'void_payment',
                'table_name' => 'payments',
                'record_id' => $p->id,
                'old_data' => json_encode($p),
                'note' => 'Pembayaran ' . $p->doc_no . ' dibatalkan',
            ]);
        });
        Notification::make()->title('Pembayaran ' . $p->doc_no . ' dibatalkan')->success()->send();
    }

    private function friendly(Throwable $e): string
    {
        if (preg_match('/ERROR:\s+(.+?)\s+\(Connection:/s', $e->getMessage(), $m)) {
            return $m[1];
        }

        return $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan. Hubungi admin.';
    }
}