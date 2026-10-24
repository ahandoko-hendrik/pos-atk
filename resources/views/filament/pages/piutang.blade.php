<x-filament-panels::page>
    @php
        $rp = fn ($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
        $aging = $this->agingRows();
    @endphp

    <div style="display:grid; gap:16px; grid-template-columns: 1fr 1fr;">
        <div>
            <x-filament::section>
                <div style="font-size:18px; font-weight:700;">Pelanggan yang masih berutang</div>

                @if ($aging->isEmpty())
                    <div style="margin-top:12px;">Tidak ada piutang. Semua nota sudah lunas.</div>
                @else
                    <table style="width:100%; margin-top:12px; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="text-align:left; border-bottom:1px solid #8886;">
                                <th>Pelanggan</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Belum jatuh tempo</th>
                                <th style="text-align:right;">Telat 1-30</th>
                                <th style="text-align:right;">Telat 31-60</th>
                                <th style="text-align:right;">Telat &gt;60</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aging as $r)
                                <tr wire:key="a-{{ $r->customer_id }}" style="border-bottom:1px solid #8884;">
                                    <td>{{ $r->customer_name }}<br><small>{{ $r->customer_code }}</small></td>
                                    <td style="text-align:right;"><b>{{ $rp($r->total_piutang) }}</b></td>
                                    <td style="text-align:right;">{{ $rp($r->belum_jatuh_tempo ?? 0) }}</td>
                                    <td style="text-align:right;">{{ $rp($r->telat_1_30 ?? 0) }}</td>
                                    <td style="text-align:right;">{{ $rp($r->telat_31_60 ?? 0) }}</td>
                                    <td style="text-align:right; color:#dc2626;">{{ $rp($r->telat_lebih_60 ?? 0) }}</td>
                                    <td><x-filament::button size="xs" wire:click="pilih({{ $r->customer_id }})">Pilih</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        </div>

        <div>
            @if ($customerId)
                @php $inv = $this->invoices(); @endphp
                <x-filament::section>
                    <div style="font-size:18px; font-weight:700;">{{ $this->customerName() }}</div>
                    <div style="margin-top:4px;">Sisa piutang: <b>{{ $rp($this->totalOutstanding()) }}</b></div>

                    @if ($inv->isEmpty())
                        <div style="margin-top:12px;">Pelanggan ini tidak punya nota yang belum lunas.</div>
                    @else
                        <table style="width:100%; margin-top:12px; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="text-align:left; border-bottom:1px solid #8886;">
                                    <th>Nota</th><th>Tanggal</th><th style="text-align:right;">Total</th><th style="text-align:right;">Dibayar</th><th style="text-align:right;">Sisa</th><th>Jatuh tempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inv as $i)
                                    <tr wire:key="i-{{ $i->sale_id }}" style="border-bottom:1px solid #8884;">
                                        <td>{{ $i->doc_no }}</td>
                                        <td>{{ date('d/m/Y', strtotime($i->sale_date)) }}</td>
                                        <td style="text-align:right;">{{ number_format($i->total, 0, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ number_format($i->paid_total, 0, ',', '.') }}</td>
                                        <td style="text-align:right;"><b>{{ number_format($i->outstanding, 0, ',', '.') }}</b></td>
                                        <td>
                                            {{ date('d/m/Y', strtotime($i->eff_due_date)) }}
                                            @if ($i->days_overdue > 0)
                                                <br><small style="color:#dc2626;">telat {{ $i->days_overdue }} hari</small>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div style="margin-top:16px; font-weight:600;">Terima pembayaran</div>

                        <div style="margin-top:8px;">Jumlah (Rp)</div>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="0" wire:model="amount" />
                        </x-filament::input.wrapper>
                        <div style="margin-top:6px;">
                            <x-filament::button size="xs" color="gray" wire:click="lunasi">Lunasi semua</x-filament::button>
                        </div>

                        <div style="margin-top:8px;">Metode</div>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="method">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="qris">QRIS</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>

                        <div style="margin-top:8px;">Referensi (opsional, mis. no. transfer)</div>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="reference" />
                        </x-filament::input.wrapper>

                        <div style="margin-top:12px;">
                            <x-filament::button wire:click="terima">Terima pembayaran</x-filament::button>
                        </div>
                        <div style="margin-top:6px; font-size:12px;">Pembayaran dialokasikan ke nota tertua lebih dulu. Pembayaran tunai butuh shift yang sedang terbuka.</div>
                    @endif
                </x-filament::section>

                @php $pays = $this->recentPayments(); @endphp
                @if ($pays->isNotEmpty())
                    <x-filament::section style="margin-top:16px;">
                        <div style="font-weight:600;">Pembayaran terakhir</div>
                        <table style="width:100%; margin-top:8px; border-collapse:collapse; font-size:13px;">
                            @foreach ($pays as $p)
                                <tr wire:key="p-{{ $p->id }}" style="border-bottom:1px solid #8884;">
                                    <td>{{ $p->doc_no }}</td>
                                    <td>{{ date('d/m/Y H:i', strtotime($p->paid_at)) }}</td>
                                    <td>{{ strtoupper($p->method) }}</td>
                                    <td style="text-align:right;">{{ number_format($p->amount, 0, ',', '.') }}</td>
                                    <td>
                                        @if ($p->status === 'void')
                                            <small style="color:#dc2626;">dibatalkan</small>
                                        @elseif (auth()->user()?->isAdmin())
                                            <x-filament::button size="xs" color="danger" wire:click="batalkan({{ $p->id }})" wire:confirm="Batalkan pembayaran {{ $p->doc_no }}? Sisa piutang akan kembali.">Batalkan</x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </x-filament::section>
                @endif
            @else
                <x-filament::section>Pilih satu pelanggan di sebelah kiri untuk melihat notanya dan menerima pembayaran.</x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>