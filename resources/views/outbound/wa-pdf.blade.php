<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outbound - WMS Avian</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 15px; line-height: 1.2; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header-table td { border: none; vertical-align: top; padding: 0; }

        .form-info-box {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 9px;
            width: 140px;
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

        .label-col { width: 28%; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .total-row td { font-weight: bold; background: #f0f0f0; }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
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

{{-- Footer --}}
<div id="footer">
    <img src="{{ $footerPath }}" style="width:100%;" alt="">
</div>

{{-- Header --}}
<table class="header-table">
    <tr>
        <td style="width:18%; padding-bottom:4px;">
            <img src="{{ $logoPath }}" width="130" alt="Avian Logo">
        </td>
        <td style="width:58%; text-align:center; vertical-align:middle;">
            <div class="main-title">DOKUMEN PENGAMBILAN BARANG (OUTBOUND)</div>
            <div class="main-title">WMS PT AVIAN BRANDS</div>
            <div class="sub-title">Gudang: {{ $warehouseName }}</div>
        </td>
        <td style="width:22%; text-align:right; vertical-align:top;">
            <div class="form-info-box">
                <strong>Waktu:</strong> {{ now()->format('d M Y, H:i') }}<br>
                <strong>Operator:</strong> {{ $operator }}<br>
                <strong>Total Item:</strong> {{ count($results) }} jenis
            </div>
        </td>
    </tr>
</table>

{{-- Info Outbound --}}
<table>
    <tr>
        <th class="section-header" colspan="2">INFORMASI PENGAMBILAN BARANG</th>
    </tr>
    <tr>
        <td class="label-col"><strong>Waktu</strong></td>
        <td>{{ $now }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Operator</strong></td>
        <td>{{ $operator }}</td>
    </tr>
    <tr>
        <td class="label-col"><strong>Gudang</strong></td>
        <td>{{ $warehouseName }}</td>
    </tr>
    @if($notes)
    <tr>
        <td class="label-col"><strong>Catatan</strong></td>
        <td>{{ $notes }}</td>
    </tr>
    @endif
</table>

{{-- Detail Item --}}
<table>
    <thead>
        <tr>
            <th class="section-header" colspan="5">DETAIL ITEM YANG DIKELUARKAN</th>
        </tr>
        <tr>
            <th width="22" class="text-center">#</th>
            <th>SKU / Nama Item</th>
            <th width="55" class="text-center">Qty</th>
            <th>Diambil dari Cell</th>
            <th width="80" class="text-center">Tgl Masuk</th>
        </tr>
    </thead>
    <tbody>
        @php $totalQty = 0; @endphp
        @foreach($results as $i => $result)
        @php $totalQty += $result['quantity']; @endphp
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td>
                <strong>{{ $result['item_name'] }}</strong><br>
                <span style="font-size:9px;color:#666;">{{ $result['item_sku'] }}</span>
            </td>
            <td class="text-center"><strong>{{ number_format($result['quantity']) }}</strong></td>
            <td>
                @foreach($result['picks'] as $pick)
                    <span>Cell {{ $pick['cell_code'] ?? ($pick['cell'] ?? '—') }}: {{ number_format($pick['take_qty'] ?? ($pick['quantity'] ?? 0)) }} unit</span>@if(!$loop->last)<br>@endif
                @endforeach
            </td>
            <td class="text-center">
                @foreach($result['picks'] as $pick)
                    @if(!empty($pick['inbound_date']))
                        <span style="font-size:9px;">{{ $pick['inbound_date'] }}</span>@if(!$loop->last)<br>@endif
                    @endif
                @endforeach
            </td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2" class="text-right">TOTAL:</td>
            <td class="text-center">{{ number_format($totalQty) }} unit</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

{{-- Persetujuan Supervisor (jika ada) --}}
@if(!empty($requestNumber))
<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
    <tr>
        <td colspan="2" style="background:#f0f0f0;font-weight:bold;text-align:center;padding:5px;font-size:11px;border:1px solid #000;">
            PERSETUJUAN SUPERVISOR
        </td>
    </tr>
    <tr>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;width:30%;">No. Request</td>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">{{ $requestNumber }}</td>
    </tr>
    <tr>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">Disetujui Oleh</td>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">{{ $approvedBy ?? '—' }}</td>
    </tr>
    <tr>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">Tanggal Persetujuan</td>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">{{ $approvedAt ?? '—' }}</td>
    </tr>
    @if(!empty($signaturePath))
    <tr>
        <td style="border:1px solid #000;padding:4px 6px;font-size:10px;">Tanda Tangan</td>
        <td style="border:1px solid #000;padding:6px 6px;text-align:center;">
            <img src="{{ $signaturePath }}" style="max-height:60px;max-width:180px;">
        </td>
    </tr>
    @endif
</table>
@endif

{{-- Tanda Tangan Operator --}}
<table class="signature-table">
    <tr>
        <td>
            <div class="signature-title">Dibuat Oleh</div>
            <div class="signature-line">{{ $operator }}</div>
        </td>
        <td>
            <div class="signature-title">Diterima Oleh</div>
            <div class="signature-line">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
        </td>
    </tr>
</table>

</body>
</html>
