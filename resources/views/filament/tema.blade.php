<style>
    :root { --pos-blue:#2563eb; --pos-blue-d:#1d4ed8; }

    /* ---------- latar halaman ---------- */
    html:not(.dark) body, html:not(.dark) .fi-body { background:#f1f5f9; }
    .fi-main { animation:posFade .3s ease both; }
    @keyframes posFade { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }

    /* ---------- menu samping ---------- */
    .fi-sidebar { background:linear-gradient(180deg,#0f172a 0%,#111c36 100%) !important; border-right:0 !important; }
    .fi-sidebar-header { background:transparent !important; box-shadow:none !important; border-bottom:1px solid rgba(255,255,255,.08) !important; }
    .fi-sidebar-header, .fi-sidebar-header * { color:#fff; }
    .fi-sidebar-group-label { color:#94a3b8 !important; font-size:11px !important; font-weight:700 !important; letter-spacing:.08em; text-transform:uppercase; }
    .fi-sidebar-item-btn { border-radius:10px !important; transition:background .15s, transform .12s; }
    .fi-sidebar-item-btn:hover { background:rgba(255,255,255,.09) !important; transform:translateX(2px); }
    .fi-sidebar-item-label, .fi-sidebar-item-icon { color:#cbd5e1 !important; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background:linear-gradient(135deg,#3b82f6,#2563eb) !important; box-shadow:0 6px 16px rgba(37,99,235,.4); }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color:#fff !important; font-weight:700; }
    .fi-sidebar-nav { scrollbar-width:thin; scrollbar-color:#334155 transparent; }

    /* ---------- bilah atas ---------- */
    .fi-topbar nav { backdrop-filter:blur(8px); }
    html:not(.dark) .fi-topbar nav { background:rgba(255,255,255,.88) !important; box-shadow:0 1px 0 #e2e8f0 !important; }
    .fi-logo { font-weight:800; letter-spacing:-.01em; }

    /* ---------- kartu, tabel, kotak angka ---------- */
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat {
        border-radius:16px !important;
        box-shadow:0 1px 2px rgba(15,23,42,.05), 0 6px 18px rgba(15,23,42,.06) !important;
    }
    .fi-wi-stats-overview-stat { transition:transform .16s, box-shadow .16s; }
    .fi-wi-stats-overview-stat:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(37,99,235,.16) !important; }
    .fi-ta-row { transition:background .12s; }
    .fi-header-heading { font-weight:800 !important; letter-spacing:-.01em; }

    /* ---------- tombol dan isian ---------- */
    .fi-btn { border-radius:10px !important; transition:transform .1s, box-shadow .15s, filter .15s; }
    .fi-btn:hover { filter:brightness(1.05); }
    .fi-btn:active { transform:scale(.97); }
    .fi-input-wrp { border-radius:10px !important; }

    @media (prefers-reduced-motion: reduce) {
        .fi-main, .fi-sidebar-item-btn, .fi-btn, .fi-wi-stats-overview-stat { animation:none !important; transition:none !important; }
    }
</style>