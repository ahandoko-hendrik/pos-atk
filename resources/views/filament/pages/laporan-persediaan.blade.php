<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $tot = $this->totals($rows);
        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    @endphp

    <x-filament::section>
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <div>Dari tanggal</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="dari" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <div>Sampai tanggal</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="sampai" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <div>Lokasi</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="lokasi">
                        <option value="">Semua lokasi</option>
                        @foreach ($this->locations() as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <x-filament::button wire:click="unduh" icon="heroicon-o-arrow-down-tray">Unduh Excel</x-filament::button>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div style="display:grid; gap:12px; grid-template-columns: repeat(5, 1fr); text-align:center;">
            <div><small>Persediaan awal</small><br><b>Rp{{ $rp($tot['awal']) }}</b></div>
            <div><small>Pembelian</small><br><b>Rp{{ $rp($tot['beli']) }}</b></div>
            <div><small>Penjualan (HPP)</small><br><b>Rp{{ $rp($tot['jual']) }}</b></div>
            <div><small>Lain-lain</small><br><b>Rp{{ $rp($tot['lain']) }}</b></div>
            <div><small>Persediaan akhir</small><br><b>Rp{{ $rp($tot['akhir']) }}</b></div>
        </div>
    </x-filament::section>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:900px;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #8886;">
                    <th>SKU</th><th>Nama barang</th><th>Satuan</th>
                    <th style="text-align:right;">Awal</th>
                    <th style="text-align:right;">Beli</th>
                    <th style="text-align:right;">Jual</th>
                    <th style="text-align:right;">Lain</th>
                    <th style="text-align:right;">Akhir</th>
                    <th style="text-align:right;">Nilai akhir (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr wire:key="lp-{{ $r->sku }}" style="border-bottom:1px solid #8884;">
                        <td>{{ $r->sku }}</td>
                        <td>{{ $r->nama }}</td>
                        <td>{{ $r->satuan }}</td>
                        <td style="text-align:right;">{{ $q($r->awal_qty) }}</td>
                        <td style="text-align:right;">{{ $q($r->pembelian_qty) }}</td>
                        <td style="text-align:right;">{{ $q($r->penjualan_qty) }}</td>
                        <td style="text-align:right;">{{ $q($r->lain_qty) }}</td>
                        <td style="text-align:right;"><b>{{ $q($r->akhir_qty) }}</b></td>
                        <td style="text-align:right;">{{ $rp($r->akhir_nilai) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="padding:12px;">Belum ada pergerakan stok pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>