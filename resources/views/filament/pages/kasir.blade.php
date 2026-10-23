<x-filament-panels::page>
    <div x-data
         x-on:cetak-struk.window="let f = document.createElement('iframe'); f.style.cssText = 'position:fixed;width:0;height:0;border:0;'; f.src = $event.detail.url; document.body.appendChild(f); setTimeout(() => f.remove(), 15000)"
         style="display:grid; gap:16px; grid-template-columns: 2fr 1fr;">
        <div>
            <x-filament::input.wrapper>
                <x-filament::input type="text" id="barcode" wire:model="barcode" wire:keydown.enter="addByBarcode"
                    autofocus placeholder="Scan barcode atau ketik SKU, lalu Enter" />
            </x-filament::input.wrapper>

            <div style="margin-top:8px;">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.live.debounce.300ms="search" placeholder="Atau cari nama barang" />
                </x-filament::input.wrapper>
            </div>

            @foreach ($this->results() as $p)
                <div wire:key="r-{{ $p->id }}" style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #8884;">
                    <span>{{ $p->name }} <small>({{ $p->sku }})</small></span>
                    <span style="display:flex; gap:6px;">
                        <x-filament::button size="xs" wire:click="addProduct({{ $p->id }})">+ {{ $p->base_unit }}</x-filament::button>
                        @if ($p->alt_unit)
                            <x-filament::button size="xs" color="gray" wire:click="addProduct({{ $p->id }}, 'alternatif')">+ {{ $p->alt_unit }}</x-filament::button>
                        @endif
                    </span>
                </div>
            @endforeach

            <table style="width:100%; margin-top:16px; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #8886;">
                        <th>Barang</th><th>Jumlah</th><th>Satuan</th><th style="text-align:right;">Harga</th><th style="text-align:right;">Subtotal</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cart as $key => $line)
                        <tr wire:key="c-{{ $key }}" style="border-bottom:1px solid #8884;">
                            <td>{{ $line['name'] }}</td>
                            <td style="width:110px;">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="number" min="1" wire:model.live.debounce.400ms="cart.{{ $key }}.qty" />
                                </x-filament::input.wrapper>
                            </td>
                            <td>{{ $line['unit'] }}</td>
                            <td style="text-align:right;">{{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                            <td style="text-align:right;">{{ number_format((float) $line['qty'] * (float) $line['unit_price'] - (float) $line['discount'], 0, ',', '.') }}</td>
                            <td><x-filament::button size="xs" color="danger" wire:click="removeLine('{{ $key }}')">Hapus</x-filament::button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            <x-filament::section>
                <div style="font-size:28px; font-weight:700;">Rp{{ number_format($this->total(), 0, ',', '.') }}</div>

                <div style="margin-top:12px;">Tingkat harga</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="tierCode">
                        @foreach ($this->tiers() as $t)
                            <option value="{{ $t->code }}">{{ $t->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <div style="margin-top:12px;">Pelanggan</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="customerId">
                        <option value="">Umum (tanpa pelanggan)</option>
                        @foreach ($this->customers() as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @php $cust = $this->customer(); @endphp
                @if ($cust)
                    <div style="margin-top:8px; font-size:13px;">
                        Piutang berjalan: <b>Rp{{ number_format($this->outstanding(), 0, ',', '.') }}</b>
                        @if ($cust->allow_credit && $cust->credit_limit > 0)
                            &middot; Batas kredit Rp{{ number_format($cust->credit_limit, 0, ',', '.') }}
                        @endif
                    </div>
                    @if ($cust->allow_credit)
                        <label style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                            <x-filament::input.checkbox wire:model.live="useCredit" /> Penjualan tempo
                        </label>
                        @if ($useCredit)
                            <div style="margin-top:8px;">Jatuh tempo</div>
                            <x-filament::input.wrapper>
                                <x-filament::input type="date" wire:model="dueDate" />
                            </x-filament::input.wrapper>
                        @endif
                    @else
                        <div style="margin-top:8px; font-size:13px;">Pelanggan ini tidak boleh tempo.</div>
                    @endif
                @endif

                <div style="margin-top:12px;">Diskon nota (Rp)</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="discount" />
                </x-filament::input.wrapper>

                <div style="margin-top:12px;">Metode bayar</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="method">
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <div style="margin-top:12px;">{{ $useCredit ? 'Uang muka / dibayar sekarang (Rp)' : 'Uang diterima (Rp)' }}</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="cash" />
                </x-filament::input.wrapper>

                <div style="margin-top:12px;">
                    @if ($useCredit)
                        Sisa tagihan (tempo): <b>Rp{{ number_format(max(0, $this->total() - (float) $cash), 0, ',', '.') }}</b>
                    @else
                        Kembalian: <b>Rp{{ number_format(max(0, (float) $cash - $this->total()), 0, ',', '.') }}</b>
                    @endif
                </div>

                <div style="margin-top:16px;">
                    <x-filament::button wire:click="checkout" :disabled="! $cart" style="width:100%;">Bayar</x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>