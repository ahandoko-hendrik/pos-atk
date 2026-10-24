<x-filament-panels::page>
    @php
        $shift = $this->shift();
        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
        $qf = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    @endphp

    <style>
        .kc-wrap { display:grid; gap:16px; grid-template-columns: minmax(0,3fr) minmax(330px,2fr); align-items:start; }
        @media (max-width: 900px) { .kc-wrap { grid-template-columns: 1fr; } }
        .kc-chips { display:flex; gap:8px; overflow-x:auto; padding:10px 0; }
        .kc-chip { border:1px solid #8886; border-radius:999px; padding:6px 14px; font-size:13px; font-weight:600; white-space:nowrap; cursor:pointer; background:transparent; color:inherit; }
        .kc-chip.on { background:#1e293b; color:#fff; border-color:#1e293b; }
        .kc-grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); max-height:66vh; overflow-y:auto; padding:2px 4px 2px 2px; }
        .kc-card { border:1px solid #8885; border-radius:12px; padding:12px; cursor:pointer; display:flex; flex-direction:column; justify-content:space-between; min-height:120px; }
        .kc-card:hover { border-color:#059669; box-shadow:0 0 0 2px #05966933; }
        .kc-card.out { opacity:.55; }
        .kc-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 7px; border-radius:6px; background:#8882; }
        .kc-name { font-weight:700; font-size:14px; margin-top:6px; line-height:1.25; }
        .kc-foot { display:flex; justify-content:space-between; align-items:center; border-top:1px solid #8883; margin-top:8px; padding-top:6px; }
        .kc-price { color:#059669; font-weight:800; font-size:14px; }
        .kc-stock { font-size:11px; font-weight:700; opacity:.75; }
        .kc-stock.low { color:#dc2626; opacity:1; }
        .kc-alt { font-size:11px; font-weight:700; border:1px solid #8886; border-radius:6px; padding:1px 8px; margin-top:6px; background:transparent; color:inherit; cursor:pointer; }
        .kc-box { border:1px solid #8884; border-radius:12px; padding:14px; }
        .kc-row { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #8883; font-size:13px; }
        .kc-total { font-size:26px; font-weight:800; color:#059669; }
        .kc-btn-pay { flex:1; background:#059669; color:#fff; font-weight:700; border-radius:10px; padding:12px; border:0; cursor:pointer; font-size:15px; }
        .kc-btn-pay:disabled { opacity:.45; cursor:not-allowed; }
        .kc-btn-reset { border:1px solid #dc2626; color:#dc2626; background:transparent; font-weight:700; border-radius:10px; padding:12px 18px; cursor:pointer; }
    </style>

    <div x-data
         x-on:cetak-struk.window="let f = document.createElement('iframe'); f.style.cssText = 'position:fixed;width:0;height:0;border:0;'; f.src = $event.detail.url; document.body.appendChild(f); setTimeout(() => f.remove(), 15000)"
         x-on:fokus-barcode.window="$nextTick(() => document.getElementById('search')?.focus())"
         x-on:keydown.f2.window.prevent="document.getElementById('search')?.focus()"
         x-on:keydown.f9.window.prevent="setTimeout(() => $wire.checkout(), 500)">

        @if (! $shift)
            <x-filament::section>
                <div style="font-size:20px; font-weight:700;">Buka shift kasir</div>
                <div style="margin-top:4px; font-size:13px;">Hitung uang yang ada di laci sekarang, lalu isi di bawah ini sebelum mulai berjualan.</div>

                <div style="margin-top:12px;">Uang awal di laci (Rp)</div>
                <x-filament::input.wrapper style="max-width:320px;">
                    <x-filament::input type="number" min="0" wire:model="openingCash" wire:keydown.enter="openShift" />
                </x-filament::input.wrapper>

                <div style="margin-top:16px;">
                    <x-filament::button wire:click="openShift">Buka shift</x-filament::button>
                </div>
            </x-filament::section>
        @else
            <div class="kc-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px; margin-bottom:12px;">
                <div>
                    Shift dibuka {{ date('d/m H:i', strtotime($shift->opened_at)) }} &middot;
                    Uang awal <b>Rp{{ $rp($shift->opening_cash) }}</b> &middot;
                    Penjualan shift ini <b>Rp{{ $rp($this->shiftSales()) }}</b>
                </div>
                <button type="button" class="kc-chip" wire:click="toggleClose">{{ $showClose ? 'Batal tutup shift' : 'Tutup shift' }}</button>
            </div>

            @if ($showClose)
                <div class="kc-box" style="margin-bottom:12px;">
                    <div style="font-weight:700;">Tutup shift</div>
                    <div style="margin-top:8px;">Uang yang ada di laci sekarang (Rp)</div>
                    <x-filament::input.wrapper style="max-width:320px;">
                        <x-filament::input type="number" min="0" wire:model="countedCash" />
                    </x-filament::input.wrapper>
                    <div style="margin-top:8px;">
                        <x-filament::button color="danger" wire:click="closeShift" wire:confirm="Tutup shift sekarang? Setelah ditutup, penjualan baru butuh shift baru.">Tutup shift</x-filament::button>
                    </div>
                </div>
            @endif

            <div class="kc-wrap">
                {{-- ============ KIRI: katalog produk ============ --}}
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" id="search" wire:model.live.debounce.300ms="search" wire:keydown.enter="scanOrAdd"
                            autofocus placeholder="Scan barcode / ketik SKU atau nama produk (F2), lalu Enter" />
                    </x-filament::input.wrapper>

                    <div class="kc-chips">
                        <button type="button" class="kc-chip {{ $kategori === '' ? 'on' : '' }}" wire:click="$set('kategori', '')">Semua</button>
                        @foreach ($this->categories() as $c)
                            <button type="button" wire:key="k-{{ $c->id }}" class="kc-chip {{ (string) $kategori === (string) $c->id ? 'on' : '' }}"
                                wire:click="$set('kategori', '{{ $c->id }}')">{{ $c->name }}</button>
                        @endforeach
                    </div>

                    @php $cards = $this->cards(); @endphp
                    <div class="kc-grid">
                        @forelse ($cards as $p)
                            @php
                                $habis = (float) $p->stok <= 0;
                                $menipis = $habis || ((float) $p->min_stock > 0 && (float) $p->stok <= (float) $p->min_stock);
                            @endphp
                            <div wire:key="p-{{ $p->id }}" class="kc-card {{ $habis ? 'out' : '' }}" wire:click="addProduct({{ $p->id }})">
                                <div>
                                    <span class="kc-badge">{{ $p->kategori }}</span>
                                    <div class="kc-name">{{ $p->name }}</div>
                                    @if ($p->alt_unit)
                                        <button type="button" class="kc-alt" wire:click.stop="addProduct({{ $p->id }}, 'alternatif')">+ {{ $p->alt_unit }}</button>
                                    @endif
                                </div>
                                <div class="kc-foot">
                                    <span class="kc-price">
                                        @if ($p->harga !== null) Rp {{ $rp($p->harga) }} @else - @endif
                                    </span>
                                    <span class="kc-stock {{ $menipis ? 'low' : '' }}">Stok: {{ $qf($p->stok) }}</span>
                                </div>
                            </div>
                        @empty
                            <div style="padding:20px; opacity:.7;">Tidak ada produk yang cocok. Ubah kategori atau kata pencarian.</div>
                        @endforelse
                    </div>
                    @if ($cards->count() >= 60)
                        <div style="font-size:12px; margin-top:6px; opacity:.7;">Menampilkan 60 produk pertama. Pilih kategori atau ketik nama untuk mempersempit.</div>
                    @endif
                </div>

                {{-- ============ KANAN: keranjang dan pembayaran ============ --}}
                <div>
                    <div class="kc-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <b style="font-size:16px;">Keranjang Belanja</b>
                            <span class="kc-badge" style="background:#05966922; color:#059669; font-size:12px;">{{ count($cart) }} item</span>
                        </div>

                        <div style="max-height:32vh; overflow-y:auto; margin-top:8px;">
                            @forelse ($cart as $key => $line)
                                <div class="kc-row" wire:key="c-{{ $key }}">
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:600;">{{ $line['name'] }}</div>
                                        <small>Rp {{ $rp($line['unit_price']) }} / {{ $line['unit'] }}</small>
                                    </div>
                                    <div style="width:80px;">
                                        <x-filament::input.wrapper>
                                            <x-filament::input type="number" min="1" wire:model.live.debounce.400ms="cart.{{ $key }}.qty" />
                                        </x-filament::input.wrapper>
                                    </div>
                                    <div style="width:78px; text-align:right; font-weight:700;">
                                        {{ $rp((float) $line['qty'] * (float) $line['unit_price'] - (float) $line['discount']) }}
                                    </div>
                                    <button type="button" wire:click="removeLine('{{ $key }}')" style="color:#dc2626; font-weight:700; cursor:pointer;">&#10005;</button>
                                </div>
                            @empty
                                <div style="padding:28px 0; text-align:center; font-style:italic; opacity:.6;">Keranjang transaksi masih kosong</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="kc-box" style="margin-top:12px;">
                        <div class="kc-row">
                            <span>Tingkat harga</span>
                            <x-filament::input.wrapper style="width:55%;">
                                <x-filament::input.select wire:model.live="tierCode">
                                    @foreach ($this->tiers() as $t)
                                        <option value="{{ $t->code }}">{{ $t->name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div class="kc-row">
                            <span>Pelanggan</span>
                            <x-filament::input.wrapper style="width:55%;">
                                <x-filament::input.select wire:model.live="customerId">
                                    <option value="">Umum</option>
                                    @foreach ($this->customers() as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        @php $cust = $this->customer(); @endphp
                        @if ($cust)
                            <div style="font-size:12px; padding:6px 0;">
                                Piutang berjalan: <b>Rp{{ $rp($this->outstanding()) }}</b>
                                @if ($cust->allow_credit && $cust->credit_limit > 0)
                                    &middot; batas kredit Rp{{ $rp($cust->credit_limit) }}
                                @endif
                                @if (! $cust->allow_credit)
                                    &middot; tidak boleh tempo
                                @endif
                            </div>
                            @if ($cust->allow_credit)
                                <label style="display:flex; gap:8px; align-items:center; padding:4px 0;">
                                    <x-filament::input.checkbox wire:model.live="useCredit" /> Penjualan tempo
                                </label>
                                @if ($useCredit)
                                    <div class="kc-row">
                                        <span>Jatuh tempo</span>
                                        <x-filament::input.wrapper style="width:55%;">
                                            <x-filament::input type="date" wire:model="dueDate" />
                                        </x-filament::input.wrapper>
                                    </div>
                                @endif
                            @endif
                        @endif

                        <div class="kc-row">
                            <span>Diskon nota (Rp)</span>
                            <x-filament::input.wrapper style="width:55%;">
                                <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="discount" />
                            </x-filament::input.wrapper>
                        </div>
                    </div>

                    <div class="kc-box" style="margin-top:12px;">
                        <div class="kc-row">
                            <span>Metode bayar</span>
                            <x-filament::input.wrapper style="width:55%;">
                                <x-filament::input.select wire:model="method">
                                    <option value="tunai">Tunai / Cash</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="qris">QRIS</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div class="kc-row">
                            <span>{{ $useCredit ? 'Uang muka (Rp)' : 'Uang diterima (Rp)' }}</span>
                            <x-filament::input.wrapper style="width:55%;">
                                <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="cash" />
                            </x-filament::input.wrapper>
                        </div>
                        <div class="kc-row">
                            @if ($useCredit)
                                <span>Sisa tagihan (tempo)</span>
                                <b style="color:#2563eb;">Rp {{ $rp(max(0, $this->total() - (float) $cash)) }}</b>
                            @else
                                <span>Kembalian</span>
                                <b style="color:#2563eb;">Rp {{ $rp(max(0, (float) $cash - $this->total())) }}</b>
                            @endif
                        </div>
                        <div class="kc-row" style="border-bottom:0;">
                            <b style="font-size:17px;">TOTAL BAYAR:</b>
                            <span class="kc-total">Rp {{ $rp($this->total()) }}</span>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; margin-top:12px;">
                        <button type="button" class="kc-btn-reset" wire:click="resetCart">Reset</button>
                        <button type="button" class="kc-btn-pay" wire:click="checkout" @disabled(! $cart)>Bayar &amp; Cetak Nota (F9)</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>