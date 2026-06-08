<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Order - {{ $order->do_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 15px;
            line-height: 1.2;
        }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { border: none; vertical-align: top; padding: 0; }

        .form-info-box {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 10px;
            width: 120px;
            float: right;
        }

        .title-section { text-align: center; padding-top: 8px; }
        .main-title { font-weight: bold; font-size: 13px; margin: 4px 0; }
        .sub-title { font-size: 10px; color: #444; margin-top: 3px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
            vertical-align: middle;
            font-size: 10px;
        }

        .section-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 11px;
            padding: 5px;
        }

        .label-col { width: 30%; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .total-row td { font-weight: bold; background: #f0f0f0; }

        .signature-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 10px;
            width: 33%;
        }
        .signature-title { font-weight: bold; font-size: 11px; margin-bottom: 60px; }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 10px;
        }

        #footer {
            position: fixed;
            bottom: -10px;
            left: -15px;
            right: -15px;
        }
    </style>
</head>
<body>

{{-- Footer (fixed) --}}
<div id="footer">
    <img src="{{ $footerPath }}" style="width:100%;" alt="">
</div>

{{-- Header --}}
<table class="header-table">
    <tr>
        <td style="width:18%; padding-bottom:4px;">
            <img src="{{ $logoPath }}" width="130" alt="Avian Logo">
        </td>
        <td style="width:64%; text-align:center; vertical-align:middle;">
            <div class="main-title">SURAT JALAN / DELIVERY ORDER</div>
            <div class="main-title">WMS PT AVIAN BRANDS</div>
            <div class="sub-title">Gudang: {{ $order->warehouse->name ?? '-' }}</div>
        </td>
        <td style="width:18%; text-align:right; vertical-align:top;">
            <div class="form-info-box">
                <strong>No. DO:</strong> {{ $order->do_number }}<br>
                <strong>Tgl DO:</strong> {{ $order->do_date?->format('d M Y') ?? '-' }}<br>
                <strong>Status:</strong> {{ strtoupper(str_replace('_', ' ', $order->status)) }}
            </div>
        </td>
    </tr>
</table>

{{-- Info DO --}}
<table>
    <tr>
        <th class="section-header" colspan="2">INFORMASI DELIVERY ORDER</th>
    </tr>
    <tr>
        <td class="label-col"><strong>No. Delivery Order</strong></td>
        <td>{{ $order->do_number }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Tanggal SJ</strong></td>
        <td>{{ $order->do_date?->format('d M Y') ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Diterima Tanggal</strong></td>
        <td>{{ $order->received_at?->format('d M Y, H:i') ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Diterima Oleh</strong></td>
        <td>{{ $order->receivedBy->name ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Gudang</strong></td>
        <td>{{ $order->warehouse->name ?? '-' }}</td>
    </tr>
</table>

{{-- Detail Item --}}
<table>
    <thead>
        <tr>
            <th class="section-header" colspan="8">DETAIL ITEM</th>
        </tr>
        <tr>
            <th width="20" class="text-center">#</th>
            <th>SKU / Nama Item</th>
            <th width="75">Kategori</th>
            <th width="38" class="text-center">Satuan</th>
            <th width="50" class="text-center">Qty DO</th>
            <th width="60" class="text-center">Qty Terima</th>
            <th width="42" class="text-center">Selisih</th>
            <th width="55" class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
        @php $totalOrdered = 0; $totalReceived = 0; @endphp
        @foreach ($order->items as $i => $itm)
        @php
            $diff = $itm->quantity_received - $itm->quantity_ordered;
            $totalOrdered  += $itm->quantity_ordered;
            $totalReceived += $itm->quantity_received;
            $mov = $itm->item->movement_type;
        @endphp
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td>
                <strong>{{ $itm->item->name ?? '-' }}</strong><br>
                <span style="font-size:9px;color:#666;">{{ $itm->item->sku ?? '' }}</span>
            </td>
            <td>{{ $itm->item?->category?->name ?? '-' }}</td>
            <td class="text-center">{{ $itm->item?->unit->code ?? '-' }}</td>
            <td class="text-center">{{ number_format($itm->quantity_ordered) }}</td>
            <td class="text-center">{{ $itm->quantity_received > 0 ? number_format($itm->quantity_received) : '—' }}</td>
            <td class="text-center">
                {{ $diff == 0 ? '0' : ($diff > 0 ? '+'.number_format($diff) : number_format($diff)) }}
            </td>
            <td class="text-center">{{ ucfirst(str_replace('_', ' ', $itm->status)) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" class="text-right">Total:</td>
            <td class="text-center">{{ number_format($totalOrdered) }}</td>
            <td class="text-center">{{ number_format($totalReceived) }}</td>
            <td class="text-center">
                {{ $totalOrdered == $totalReceived ? 'Sesuai' : number_format($totalReceived - $totalOrdered) }}
            </td>
            <td colspan="1"></td>
        </tr>
    </tbody>
</table>

{{-- GA Recommendation --}}
@if ($latestGa)
<table>
    <thead>
        <tr>
            <th class="section-header" colspan="5">HASIL REKOMENDASI SISTEM</th>
        </tr>
        <tr>
            <th width="20" class="text-center">#</th>
            <th>Item</th>
            <th width="50" class="text-center">Qty</th>
            <th width="75" class="text-center">Cell</th>
            <th width="50" class="text-center">Rak</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($latestGa->details as $di => $det)
        <tr>
            <td class="text-center">{{ $di + 1 }}</td>
            <td>
                <strong>{{ $det->inboundOrderItem->item->name ?? '—' }}</strong><br>
                <span style="font-size:9px;color:#666;">{{ $det->inboundOrderItem->item->sku ?? '' }}</span>
            </td>
            <td class="text-center">{{ number_format($det->quantity) }}</td>
            <td class="text-center"><strong>{{ $det->cell->code ?? '—' }}</strong></td>
            <td class="text-center">{{ $det->cell->rack->code ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif


</body>
</html>
