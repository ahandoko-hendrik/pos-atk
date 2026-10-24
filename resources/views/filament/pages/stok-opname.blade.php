<x-filament-panels::page>
    @php
        $rows = $this->products();
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
        $terisi = $this->filled()->count();
    @endphp

    <x-filament::section>
        <div style="font-weight:700;">Cara pakai</div>
        <div style="font-size:13px; margin-top:4px;">
            Pilih lokasi dan kategori, lalu isi <b>hasil hitung fisik</b> hanya untuk barang yang sudah Anda hitung.
            Barang yang dikosongkan tidak diubah. Lakukan opname di luar jam jualan agar stok sistem tidak bergerak selagi Anda menghitung.
            Menambah stok barang yang belum pernah punya harga pokok tidak bisa lewat opname; gunakan menu Pembelian.
        </div>
    </x-filament::section>

    <x-filament::section>
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <div>Lokasi</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="lokasi">
                        @foreach ($this->locations() as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <div>Kategori</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($this->categories() as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <div>Cari nama atau SKU</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.live.debounce.300ms="search" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #8886;">
                    <th>SKU</th><th>Nama barang</th><th>Satuan</th>
                    <th style="text-align:right;">Stok sistem</th>
                    <th style="width:150px;">Hasil hitung fisik</th>
                    <th style="text-align:right;">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $p)
                    @php
                        $c = $counts[$p->id] ?? '';
                        $diff = ($c !== '' && is_numeric($c)) ? (float) $c - (float) $p->qty : null;
                    @endphp
                    <tr wire:key="o-{{ $p->id }}" style="border-bottom:1px solid #8884;">
                        <td>{{ $p->sku }}</td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->base_unit }}</td>
                        <td style="text-align:right;">{{ $q($p->qty) }}</td>
                        <td>
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="0" step="any" placeholder="belum dihitung"
                                    wire:model.live.debounce.500ms="counts.{{ $p->id }}" />
                            </x-filament::input.wrapper>
                        </td>
                        <td style="text-align:right; {{ $diff !== null && $diff != 0 ? 'color:#dc2626; font-weight:700;' : '' }}">
                            {{ $diff === null ? '' : ($diff > 0 ? '+' : '') . $q($diff) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:12px;">Tidak ada barang. Ubah kategori atau kata pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->count() >= 200)
        <div style="font-size:12px;">Hanya 200 barang pertama yang ditampilkan. Pilih kategori atau gunakan pencarian untuk melihat sisanya.</div>
    @endif

    <x-filament::section>
        <div>Catatan (opsional)</div>
        <x-filament::input.wrapper>
            <x-filament::input type="text" wire:model="note" placeholder="mis. Opname rak pulpen, dihitung Bu Sari" />
        </x-filament::input.wrapper>

        <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
            <x-filament::button wire:click="selesaikan" wire:confirm="Selesaikan opname untuk {{ $terisi }} barang yang sudah diisi? Stok sistem akan disesuaikan dan tidak bisa dibatalkan.">
                Selesaikan opname ({{ $terisi }} barang terisi)
            </x-filament::button>
            <x-filament::button color="gray" wire:click="resetCounts">Kosongkan hitungan</x-filament::button>
        </div>
    </x-filament::section>

    @php $riwayat = $this->recent(); @endphp
    @if ($riwayat->isNotEmpty())
        <x-filament::section>
            <div style="font-weight:600;">Opname terakhir</div>
            <table style="width:100%; margin-top:8px; border-collapse:collapse; font-size:13px;">
                @foreach ($riwayat as $o)
                    <tr wire:key="h-{{ $o->doc_no }}" style="border-bottom:1px solid #8884;">
                        <td>{{ $o->doc_no }}</td>
                        <td>{{ date('d/m/Y', strtotime($o->opname_date)) }}</td>
                        <td>{{ $o->item }} barang dihitung</td>
                        <td>{{ $o->selisih }} barang berselisih</td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>