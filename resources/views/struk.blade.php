<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk {{ $sale->doc_no }}</title>
    <style>
        @page { size: 58mm auto; margin: 2mm; }
        body { font-family: monospace; font-size: 11px; width: 54mm; }
        hr { border: 0; border-top: 1px dashed #000; }
    </style>
</head>
<body onload="window.print()">
    <b>CV PL MAJU</b><br>
    {{ $sale->doc_no }}<br>
    {{ date('d/m/Y H:i', strtotime($sale->sale_date)) }}<br>
    @if ($customerName)
        Pelanggan: {{ $customerName }}<br>
    @endif
    <hr>
    @foreach ($items as $i)
        {{ $i->name }}<br>
        {{ rtrim(rtrim((string) $i->qty, '0'), '.') }} x {{ number_format($i->unit_price, 0, ',', '.') }}
        = {{ number_format($i->line_total, 0, ',', '.') }}<br>
    @endforeach
    <hr>
    <b>TOTAL {{ number_format($sale->total, 0, ',', '.') }}</b><br>
    @foreach ($payments as $p)
        {{ strtoupper($p->method) }} {{ number_format($p->amount, 0, ',', '.') }}<br>
    @endforeach
    @if ($sale->total > $sale->paid_total)
        <b>SISA TAGIHAN {{ number_format($sale->total - $sale->paid_total, 0, ',', '.') }}</b><br>
        @if ($sale->due_date)
            Jatuh tempo {{ date('d/m/Y', strtotime($sale->due_date)) }}<br>
        @endif
    @endif
    <hr>
    Terima kasih
</body>
</html>