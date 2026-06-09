<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Permintaan Outbound - {{ $obr->request_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; color: #111; }
        h2 { font-size: 14px; margin: 0 0 4px 0; }
        .sub { font-size: 10px; color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f0f0f0; font-size: 10px; padding: 5px 8px; border: 1px solid #ccc; text-align: left; }
        td { padding: 5px 8px; border: 1px solid #ccc; font-size: 11px; vertical-align: middle; }
        .info-table td:first-child { width: 32%; color: #555; font-weight: bold; }
        .badge { display: inline-block; background: #d4edda; color: #155724; padding: 1px 6px; border-radius: 3px; font-size: 10px; }
        .footer { margin-top: 20px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>

<h2>PERMINTAAN OUTBOUND</h2>
<div class="sub">WMS PT Avian Brands &nbsp;·&nbsp; {{ $obr->warehouse->name ?? '-' }}</div>

<table class="info-table">
    <tr><td>No. Request</td><td><strong>{{ $obr->request_number }}</strong></td></tr>
    <tr><td>Operator</td><td>{{ $obr->operator->name ?? '-' }}</td></tr>
    <tr><td>Gudang</td><td>{{ $obr->warehouse->name ?? '-' }}</td></tr>
    <tr><td>Waktu Pengajuan</td><td>{{ $waktu }}</td></tr>
    @if($obr->notes)
    <tr><td>Catatan</td><td>{{ $obr->notes }}</td></tr>
    @endif
    <tr><td>Status</td><td><span class="badge">Menunggu Persetujuan</span></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width:30px">#</th>
            <th>Nama Item</th>
            <th>SKU</th>
            <th style="width:80px">Kategori</th>
            <th style="width:60px; text-align:center">Qty</th>
            <th style="width:40px; text-align:center">Satuan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($obr->items as $i => $it)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $it->item->name ?? '-' }}</strong></td>
            <td style="color:#666;font-size:10px;">{{ $it->item->sku ?? '-' }}</td>
            <td style="font-size:10px;">{{ $it->item->category?->name ?? '-' }}</td>
            <td style="text-align:center">{{ number_format($it->quantity_requested) }}</td>
            <td style="text-align:center">{{ $it->item->unit?->code ?? '-' }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="4" style="text-align:right; font-weight:bold;">Total Item:</td>
            <td style="text-align:center; font-weight:bold;">{{ $obr->items->sum('quantity_requested') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    Dokumen ini dikirim otomatis oleh sistem WMS Avian. Mohon segera lakukan persetujuan melalui aplikasi.
</div>

</body>
</html>
