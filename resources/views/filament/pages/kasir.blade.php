<x-filament-panels::page>
    @php
        $shift = $this->shift();
        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
        $qf = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
        $total = $this->total();
    @endphp

    <style>
        .kc-scope { --kc-surface:#fff; --kc-soft:#f8fafc; --kc-line:#e2e8f0; --kc-muted:#64748b; --kc-primary:#2563eb; --kc-primary-d:#1d4ed8; --kc-accent:#2563eb; --kc-danger:#dc2626; --kc-warn:#d97706; --kc-ring:rgba(37,99,235,.28); --kc-shadow:0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(15,23,42,.06); }
        .dark .kc-scope { --kc-surface:#111827; --kc-soft:#0b1220; --kc-line:#27324a; --kc-muted:#9ca3af; --kc-ring:rgba(59,130,246,.35); --kc-shadow:0 1px 2px rgba(0,0,0,.4), 0 6px 16px rgba(0,0,0,.35); }

        @keyframes kcUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
        @keyframes kcSlide { from { opacity:0; transform:translateX(14px); } to { opacity:1; transform:none; } }
        @keyframes kcFlash { 0% { transform:scale(1.14); color:var(--kc-accent); } 100% { transform:scale(1); } }
        @keyframes kcPulse { 0% { box-shadow:0 0 0 0 rgba(59,130,246,.6); } 100% { box-shadow:0 0 0 10px rgba(59,130,246,0); } }
        @keyframes kcDash { to { stroke-dashoffset:0; } }
        @keyframes kcToast { from { opacity:0; transform:translateX(30px) scale(.96); } to { opacity:1; transform:none; } }

        .kc-wrap { display:grid; gap:16px; grid-template-columns:minmax(0,3fr) minmax(340px,2fr); align-items:start; }
        @media (max-width:1000px) { .kc-wrap { grid-template-columns:1fr; } .kc-side { position:static !important; max-height:none !important; } }

        .kc-bar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:10px 14px; border:1px solid var(--kc-line); border-radius:14px; background:var(--kc-surface); box-shadow:var(--kc-shadow); font-size:13px; margin-bottom:14px; }
        .kc-dot { display:inline-block; width:9px; height:9px; border-radius:50%; background:#3b82f6; margin-right:8px; animation:kcPulse 1.8s infinite; }

        .kc-searchbox { position:relative; transition:box-shadow .2s, border-color .2s; border:1px solid var(--kc-line); border-radius:14px; background:var(--kc-surface); box-shadow:var(--kc-shadow); }
        .kc-searchbox:focus-within { border-color:var(--kc-primary); box-shadow:0 0 0 4px var(--kc-ring); }
        .kc-searchbox.kc-ok { border-color:var(--kc-primary); box-shadow:0 0 0 5px var(--kc-ring); }
        .kc-search { width:100%; padding:13px 56px 13px 44px; background:transparent; border:0; outline:0; font-size:15px; color:inherit; }
        .kc-sico { position:absolute; left:14px; top:50%; transform:translateY(-50%); width:20px; height:20px; color:var(--kc-muted); }
        .kc-kbd { position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; font-weight:700; border:1px solid var(--kc-line); border-radius:6px; padding:1px 7px; color:var(--kc-muted); }

        .kc-chips { display:flex; gap:8px; overflow-x:auto; padding:12px 2px 10px; scrollbar-width:thin; }
        .kc-chip { border:1px solid var(--kc-line); border-radius:999px; padding:7px 15px; font-size:13px; font-weight:600; white-space:nowrap; cursor:pointer; background:var(--kc-surface); color:inherit; transition:transform .12s, background .15s, color .15s, border-color .15s; }
        .kc-chip:hover { transform:translateY(-1px); border-color:var(--kc-primary); }
        .kc-chip:active { transform:scale(.96); }
        .kc-chip.on { background:#0f172a; color:#fff; border-color:#0f172a; }
        .dark .kc-chip.on { background:var(--kc-primary); border-color:var(--kc-primary); }

        .kc-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fill, minmax(168px, 1fr)); max-height:calc(100vh - 340px); min-height:240px; overflow-y:auto; padding:4px 6px 8px 2px; transition:opacity .15s; }
        .kc-dim { opacity:.45; }
        .kc-card { position:relative; border:1px solid var(--kc-line); border-radius:14px; padding:12px; cursor:pointer; display:flex; flex-direction:column; justify-content:space-between; min-height:128px; background:var(--kc-surface); box-shadow:var(--kc-shadow); animation:kcUp .32s ease both; animation-delay:calc(var(--i, 0) * 22ms); transition:transform .16s, box-shadow .16s, border-color .16s; }
        .kc-card:hover { transform:translateY(-3px); border-color:var(--kc-primary); box-shadow:0 10px 24px rgba(37,99,235,.18); }
        .kc-card:active { transform:scale(.96); }
        .kc-card.out { opacity:.5; }
        .kc-card.out::after { content:'HABIS'; position:absolute; top:10px; right:10px; font-size:10px; font-weight:800; color:#fff; background:var(--kc-danger); border-radius:6px; padding:1px 7px; }
        .kc-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px; background:var(--kc-soft); color:var(--kc-muted); max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .kc-name { font-weight:700; font-size:14px; margin-top:7px; line-height:1.28; }
        .kc-foot { display:flex; justify-content:space-between; align-items:center; border-top:1px dashed var(--kc-line); margin-top:9px; padding-top:7px; }
        .kc-price { color:var(--kc-primary); font-weight:800; font-size:15px; }
        .kc-stock { font-size:11px; font-weight:700; color:var(--kc-muted); }
        .kc-stock.low { color:var(--kc-warn); }
        .kc-stock.none { color:var(--kc-danger); }
        .kc-alt { font-size:11px; font-weight:700; border:1px solid var(--kc-primary); color:var(--kc-primary); border-radius:8px; padding:1px 9px; margin-top:7px; background:transparent; cursor:pointer; transition:background .15s, color .15s; }
        .kc-alt:hover { background:var(--kc-primary); color:#fff; }

        .kc-side { position:sticky; top:78px; max-height:calc(100vh - 96px); overflow-y:auto; display:flex; flex-direction:column; gap:12px; padding-right:2px; }
        .kc-box { border:1px solid var(--kc-line); border-radius:16px; padding:14px; background:var(--kc-surface); box-shadow:var(--kc-shadow); }
        .kc-h { display:flex; justify-content:space-between; align-items:center; }
        .kc-count { font-size:12px; font-weight:800; padding:2px 11px; border-radius:999px; background:rgba(37,99,235,.14); color:var(--kc-primary); display:inline-block; animation:kcFlash .35s ease; }

        .kc-lines { max-height:30vh; overflow-y:auto; margin-top:6px; }
        .kc-line { display:flex; align-items:center; gap:8px; padding:9px 0; border-bottom:1px solid var(--kc-line); animation:kcSlide .28s ease both; }
        .kc-line:last-child { border-bottom:0; }
        .kc-lname { flex:1; min-width:0; font-weight:600; font-size:13px; line-height:1.25; }
        .kc-lname small { color:var(--kc-muted); font-weight:500; }
        .kc-step { display:flex; align-items:center; gap:2px; }
        .kc-sbtn { width:28px; height:28px; border-radius:8px; border:1px solid var(--kc-line); background:var(--kc-soft); font-weight:800; font-size:15px; line-height:1; cursor:pointer; color:inherit; transition:transform .1s, background .15s, color .15s; }
        .kc-sbtn:hover { background:var(--kc-primary); color:#fff; border-color:var(--kc-primary); }
        .kc-sbtn:active { transform:scale(.88); }
        .kc-qty { width:46px; text-align:center; font-weight:700; border:1px solid var(--kc-line); border-radius:8px; padding:4px 2px; background:var(--kc-surface); color:inherit; }
        .kc-sub { width:74px; text-align:right; font-weight:800; font-size:13px; display:inline-block; animation:kcFlash .3s ease; }
        .kc-del { color:var(--kc-danger); font-weight:800; cursor:pointer; width:24px; height:24px; border-radius:6px; transition:background .15s; }
        .kc-del:hover { background:rgba(220,38,38,.12); }
        .kc-empty { text-align:center; padding:26px 0 20px; color:var(--kc-muted); font-style:italic; }
        .kc-empty svg { width:54px; height:54px; margin:0 auto 6px; opacity:.45; }

        .kc-row { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--kc-line); font-size:13px; }
        .kc-row:last-child { border-bottom:0; }
        .kc-seg { display:flex; gap:4px; padding:3px; border-radius:11px; background:var(--kc-soft); border:1px solid var(--kc-line); }
        .kc-seg button { border:0; background:transparent; padding:6px 11px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; color:inherit; transition:background .15s, color .15s, transform .1s; }
        .kc-seg button:active { transform:scale(.94); }
        .kc-seg button.on { background:var(--kc-primary); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.35); }
        .kc-in { width:150px; text-align:right; padding:7px 10px; border-radius:10px; border:1px solid var(--kc-line); background:var(--kc-surface); color:inherit; font-weight:600; transition:box-shadow .15s, border-color .15s; }
        .kc-in:focus { outline:0; border-color:var(--kc-primary); box-shadow:0 0 0 3px var(--kc-ring); }
        .kc-sel { max-width:190px; padding:7px 10px; border-radius:10px; border:1px solid var(--kc-line); background:var(--kc-surface); color:inherit; font-weight:600; }
        .kc-quick { display:flex; flex-wrap:wrap; gap:6px; padding:6px 0 8px; }
        .kc-q { border:1px solid var(--kc-line); border-radius:9px; padding:5px 11px; font-size:12px; font-weight:700; cursor:pointer; background:var(--kc-soft); color:inherit; transition:transform .1s, background .15s, color .15s; }
        .kc-q:hover { background:var(--kc-primary); color:#fff; border-color:var(--kc-primary); }
        .kc-q:active { transform:scale(.93); }
        .kc-totalrow { display:flex; justify-content:space-between; align-items:center; padding-top:10px; }
        .kc-total { font-size:30px; font-weight:900; color:var(--kc-primary); display:inline-block; animation:kcFlash .35s ease; }
        .kc-info { color:var(--kc-accent); font-weight:800; }

        .kc-switch { position:relative; display:inline-block; width:40px; height:22px; }
        .kc-switch input { opacity:0; width:0; height:0; }
        .kc-switch span { position:absolute; inset:0; background:#94a3b8; border-radius:999px; transition:background .2s; cursor:pointer; }
        .kc-switch span::before { content:''; position:absolute; width:16px; height:16px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; }
        .kc-switch input:checked + span { background:var(--kc-primary); }
        .kc-switch input:checked + span::before { transform:translateX(18px); }

        .kc-actions { display:flex; gap:10px; }
        .kc-reset { border:1px solid var(--kc-danger); color:var(--kc-danger); background:transparent; font-weight:700; border-radius:12px; padding:13px 18px; cursor:pointer; transition:background .15s, transform .1s; }
        .kc-reset:hover { background:rgba(220,38,38,.08); }
        .kc-reset:active { transform:scale(.96); }
        .kc-pay { flex:1; color:#fff; font-weight:800; font-size:15px; border:0; border-radius:12px; padding:13px; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); box-shadow:0 6px 18px rgba(37,99,235,.35); transition:transform .12s, box-shadow .15s, filter .15s; }
        .kc-pay:hover { filter:brightness(1.07); box-shadow:0 10px 24px rgba(37,99,235,.45); }
        .kc-pay:active { transform:scale(.98); }
        .kc-pay:disabled { opacity:.45; cursor:not-allowed; box-shadow:none; filter:none; }

        .kc-toast { position:fixed; right:24px; top:84px; z-index:60; display:flex; gap:12px; align-items:center; padding:14px 18px; border-radius:16px; background:var(--kc-surface); border:1px solid var(--kc-primary); box-shadow:0 14px 34px rgba(37,99,235,.28); animation:kcToast .3s ease; cursor:pointer; }
        .kc-toast svg { width:42px; height:42px; }
        .kc-checkc { stroke:var(--kc-primary); stroke-width:3; stroke-dasharray:152; stroke-dashoffset:152; animation:kcDash .55s .05s forwards; }
        .kc-checkp { stroke:var(--kc-primary); stroke-width:4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:kcDash .35s .5s forwards; }
        .kc-toast-t { font-weight:800; font-size:14px; }
        .kc-toast-s { font-size:13px; color:var(--kc-muted); }
        .kc-toast-s b { color:var(--kc-primary); font-size:18px; }

        @media (prefers-reduced-motion: reduce) {
            .kc-scope *, .kc-scope *::before, .kc-scope *::after { animation:none !important; transition:none !important; }
        }
    </style>

    <div class="kc-scope"
         x-data="{
            ok: false, doc: '', change: 0, flash: false, t: null,
            fmt(n) { return new Intl.NumberFormat('id-ID').format(n) },
            added() { this.flash = true; setTimeout(() => this.flash = false, 500) },
            done(d) { this.doc = d.doc; this.change = Number(d.change || 0); this.ok = true; clearTimeout(this.t); this.t = setTimeout(() => this.ok = false, 4000) }
         }"
         x-on:item-added.window="added()"
         x-on:nota-sukses.window="done($event.detail)"
         x-on:cetak-struk.window="let f = document.createElement('iframe'); f.style.cssText = 'position:fixed;width:0;height:0;border:0;'; f.src = $event.detail.url; document.body.appendChild(f); setTimeout(() => f.remove(), 15000)"
         x-on:fokus-barcode.window="$nextTick(() => document.getElementById('search')?.focus())"
         x-on:keydown.f2.window.prevent="document.getElementById('search')?.focus()"
         x-on:keydown.f9.window.prevent="setTimeout(() => $wire.checkout(), 500)">

        {{-- notifikasi sukses --}}
        <div x-show="ok" x-cloak x-on:click="ok = false" class="kc-toast">
            <svg viewBox="0 0 52 52">
                <circle class="kc-checkc" cx="26" cy="26" r="24" fill="none"></circle>
                <path class="kc-checkp" fill="none" d="M14 27l8 8 16-17"></path>
            </svg>
            <div>
                <div class="kc-toast-t">Nota <span x-text="doc"></span> tersimpan</div>
                <div class="kc-toast-s" x-show="change > 0">Kembalian <b x-text="'Rp ' + fmt(change)"></b></div>
            </div>
        </div>

        @if (! $shift)
            <div class="kc-box" style="max-width:520px;">
                <div style="font-size:20px; font-weight:800;">Buka shift kasir</div>
                <div style="margin-top:4px; font-size:13px; color:var(--kc-muted);">Hitung uang yang ada di laci sekarang, lalu isi di bawah ini sebelum mulai berjualan.</div>
                <div style="margin-top:14px; font-size:13px;">Uang awal di laci (Rp)</div>
                <input type="number" min="0" class="kc-in" style="width:100%; text-align:left; margin-top:4px;" wire:model="openingCash" wire:keydown.enter="openShift">
                <button type="button" class="kc-pay" style="margin-top:14px; width:100%;" wire:click="openShift">Buka shift</button>
            </div>
        @else
            <div class="kc-bar">
                <div>
                    <span class="kc-dot"></span>Shift aktif sejak {{ date('d/m H:i', strtotime($shift->opened_at)) }} &middot;
                    Uang awal <b>Rp{{ $rp($shift->opening_cash) }}</b> &middot;
                    Penjualan shift ini <b>Rp{{ $rp($this->shiftSales()) }}</b>
                </div>
                <button type="button" class="kc-chip" wire:click="toggleClose">{{ $showClose ? 'Batal tutup shift' : 'Tutup shift' }}</button>
            </div>

            @if ($showClose)
                <div class="kc-box" style="margin-bottom:14px; max-width:520px; animation:kcUp .25s ease;">
                    <div style="font-weight:800;">Tutup shift</div>
                    <div style="margin-top:8px; font-size:13px;">Uang yang ada di laci sekarang (Rp)</div>
                    <input type="number" min="0" class="kc-in" style="width:100%; text-align:left; margin-top:4px;" wire:model="countedCash">
                    <button type="button" class="kc-reset" style="margin-top:10px;" wire:click="closeShift"
                        wire:confirm="Tutup shift sekarang? Setelah ditutup, penjualan baru butuh shift baru.">Tutup shift</button>
                </div>
            @endif

            <div class="kc-wrap">
                {{-- ============ KIRI: katalog produk ============ --}}
                <div>
                    <div class="kc-searchbox" x-bind:class="flash && 'kc-ok'">
                        <svg class="kc-sico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                        <input id="search" class="kc-search" type="text" autocomplete="off" autofocus
                            wire:model.live.debounce.300ms="search" wire:keydown.enter="scanOrAdd"
                            placeholder="Scan barcode / ketik SKU atau nama produk, lalu Enter">
                        <span class="kc-kbd">F2</span>
                    </div>

                    <div class="kc-chips">
                        <button type="button" class="kc-chip {{ $kategori === '' ? 'on' : '' }}" wire:click="$set('kategori', '')">Semua</button>
                        @foreach ($this->categories() as $c)
                            <button type="button" wire:key="k-{{ $c->id }}" class="kc-chip {{ (string) $kategori === (string) $c->id ? 'on' : '' }}"
                                wire:click="$set('kategori', '{{ $c->id }}')">{{ $c->name }}</button>
                        @endforeach
                    </div>

                    @php $cards = $this->cards(); @endphp
                    <div class="kc-grid" wire:loading.class="kc-dim" wire:target="search,kategori,tierCode">
                        @forelse ($cards as $p)
                            @php
                                $habis = (float) $p->stok <= 0;
                                $menipis = ! $habis && (float) $p->min_stock > 0 && (float) $p->stok <= (float) $p->min_stock;
                            @endphp
                            <div wire:key="p-{{ $p->id }}" class="kc-card {{ $habis ? 'out' : '' }}" style="--i: {{ min($loop->index, 14) }};"
                                 wire:click="addProduct({{ $p->id }})">
                                <div>
                                    <span class="kc-badge">{{ $p->kategori }}</span>
                                    <div class="kc-name">{{ $p->name }}</div>
                                    @if ($p->alt_unit)
                                        <button type="button" class="kc-alt" wire:click.stop="addProduct({{ $p->id }}, 'alternatif')">+ {{ $p->alt_unit }}</button>
                                    @endif
                                </div>
                                <div class="kc-foot">
                                    <span class="kc-price">@if ($p->harga !== null) Rp {{ $rp($p->harga) }} @else - @endif</span>
                                    <span class="kc-stock {{ $habis ? 'none' : ($menipis ? 'low' : '') }}">Stok {{ $qf($p->stok) }}</span>
                                </div>
                            </div>
                        @empty
                            <div style="padding:24px; color:var(--kc-muted);">Tidak ada produk yang cocok. Ubah kategori atau kata pencarian.</div>
                        @endforelse
                    </div>
                    @if ($cards->count() >= 60)
                        <div style="font-size:12px; margin-top:6px; color:var(--kc-muted);">Menampilkan 60 produk pertama. Pilih kategori atau ketik nama untuk mempersempit.</div>
                    @endif
                </div>

                {{-- ============ KANAN: keranjang dan pembayaran ============ --}}
                <div class="kc-side">
                    <div class="kc-box">
                        <div class="kc-h">
                            <b style="font-size:16px;">Keranjang Belanja</b>
                            <span wire:key="cnt-{{ count($cart) }}" class="kc-count">{{ count($cart) }} item</span>
                        </div>

                        <div class="kc-lines">
                            @forelse ($cart as $key => $line)
                                @php $sub = (float) $line['qty'] * (float) $line['unit_price'] - (float) $line['discount']; @endphp
                                <div class="kc-line" wire:key="c-{{ $key }}">
                                    <div class="kc-lname">
                                        {{ $line['name'] }}<br>
                                        <small>Rp {{ $rp($line['unit_price']) }} / {{ $line['unit'] }}</small>
                                    </div>
                                    <div class="kc-step">
                                        <button type="button" class="kc-sbtn" wire:click="decLine('{{ $key }}')">&minus;</button>
                                        <input type="number" min="1" step="any" class="kc-qty" wire:model.live.debounce.500ms="cart.{{ $key }}.qty">
                                        <button type="button" class="kc-sbtn" wire:click="incLine('{{ $key }}')">+</button>
                                    </div>
                                    <span wire:key="s-{{ $key }}-{{ round($sub) }}" class="kc-sub">{{ $rp($sub) }}</span>
                                    <button type="button" class="kc-del" wire:click="removeLine('{{ $key }}')">&#10005;</button>
                                </div>
                            @empty
                                <div class="kc-empty">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"></circle><circle cx="18" cy="20" r="1.4"></circle><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6"></path></svg>
                                    Keranjang masih kosong.<br>Ketuk produk atau scan barcode.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="kc-box">
                        <div class="kc-row">
                            <span>Tingkat harga</span>
                            <div class="kc-seg">
                                @foreach ($this->tiers() as $t)
                                    <button type="button" wire:key="t-{{ $t->code }}" class="{{ $tierCode === $t->code ? 'on' : '' }}"
                                        wire:click="$set('tierCode', '{{ $t->code }}')">{{ $t->name }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="kc-row">
                            <span>Pelanggan</span>
                            <select class="kc-sel" wire:model.live="customerId">
                                <option value="">Umum</option>
                                @foreach ($this->customers() as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        @php $cust = $this->customer(); @endphp
                        @if ($cust)
                            <div style="font-size:12px; padding:6px 0; color:var(--kc-muted); animation:kcUp .25s ease;">
                                Piutang berjalan <b>Rp{{ $rp($this->outstanding()) }}</b>
                                @if ($cust->allow_credit && $cust->credit_limit > 0)
                                    &middot; batas kredit Rp{{ $rp($cust->credit_limit) }}
                                @endif
                                @if (! $cust->allow_credit)
                                    &middot; tidak boleh tempo
                                @endif
                            </div>
                            @if ($cust->allow_credit)
                                <div class="kc-row" style="animation:kcUp .25s ease;">
                                    <span>Penjualan tempo</span>
                                    <label class="kc-switch"><input type="checkbox" wire:model.live="useCredit"><span></span></label>
                                </div>
                                @if ($useCredit)
                                    <div class="kc-row" style="animation:kcUp .25s ease;">
                                        <span>Jatuh tempo</span>
                                        <input type="date" class="kc-in" wire:model="dueDate">
                                    </div>
                                @endif
                            @endif
                        @endif

                        <div class="kc-row">
                            <span>Diskon nota (Rp)</span>
                            <input type="number" min="0" class="kc-in" wire:model.live.debounce.400ms="discount">
                        </div>
                    </div>

                    <div class="kc-box">
                        <div class="kc-row">
                            <span>Metode bayar</span>
                            <div class="kc-seg">
                                <button type="button" class="{{ $method === 'tunai' ? 'on' : '' }}" wire:click="$set('method', 'tunai')">Tunai</button>
                                <button type="button" class="{{ $method === 'transfer' ? 'on' : '' }}" wire:click="$set('method', 'transfer')">Transfer</button>
                                <button type="button" class="{{ $method === 'qris' ? 'on' : '' }}" wire:click="$set('method', 'qris')">QRIS</button>
                            </div>
                        </div>
                        <div class="kc-row" style="border-bottom:0;">
                            <span>{{ $useCredit ? 'Uang muka (Rp)' : 'Uang diterima (Rp)' }}</span>
                            <input type="number" min="0" class="kc-in" wire:model.live.debounce.300ms="cash">
                        </div>
                        @if (! $useCredit && $cart)
                            <div class="kc-quick">
                                <button type="button" class="kc-q" wire:click="cashExact">Uang pas</button>
                                <button type="button" class="kc-q" wire:click="setCash(20000)">20.000</button>
                                <button type="button" class="kc-q" wire:click="setCash(50000)">50.000</button>
                                <button type="button" class="kc-q" wire:click="setCash(100000)">100.000</button>
                            </div>
                        @endif
                        <div class="kc-row" style="border-bottom:0;">
                            @if ($useCredit)
                                <span>Sisa tagihan (tempo)</span>
                                <b class="kc-info">Rp {{ $rp(max(0, $total - (float) $cash)) }}</b>
                            @else
                                <span>Kembalian</span>
                                <b class="kc-info">Rp {{ $rp(max(0, (float) $cash - $total)) }}</b>
                            @endif
                        </div>
                        <div class="kc-totalrow">
                            <b style="font-size:16px;">TOTAL BAYAR</b>
                            <span wire:key="tot-{{ round($total) }}" class="kc-total">Rp {{ $rp($total) }}</span>
                        </div>
                    </div>

                    <div class="kc-actions">
                        <button type="button" class="kc-reset" wire:click="resetCart">Reset</button>
                        <button type="button" class="kc-pay" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout" @disabled(! $cart)>
                            <span wire:loading.remove wire:target="checkout">Bayar &amp; Cetak Nota (F9)</span>
                            <span wire:loading wire:target="checkout">Memproses...</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>