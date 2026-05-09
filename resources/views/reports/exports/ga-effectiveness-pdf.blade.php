<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Efektivitas Genetic Algorithm</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2933; margin: 24px; }
        h1, h2, h3 { margin: 0; }
        h1 { font-size: 20px; color: #0b6b55; }
        h2 { font-size: 14px; margin-top: 18px; margin-bottom: 8px; color: #0b6b55; border-bottom: 1px solid #d9dee3; padding-bottom: 5px; }
        .muted { color: #6b7280; }
        .header { border-bottom: 3px solid #0b6b55; padding-bottom: 10px; margin-bottom: 14px; }
        .meta { margin-top: 6px; line-height: 1.5; }
        .summary-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .summary-grid td { width: 25%; border: 1px solid #d9dee3; padding: 9px; vertical-align: top; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: .4px; }
        .value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 3px; }
        .note { background: #f8fafc; border-left: 4px solid #0b6b55; padding: 8px 10px; margin: 10px 0; line-height: 1.55; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #0b6b55; color: #fff; font-weight: bold; padding: 7px 6px; border: 1px solid #0b6b55; text-align: left; }
        td { padding: 6px; border: 1px solid #d9dee3; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-secondary { background: #e5e7eb; color: #374151; }
        .footer { position: fixed; bottom: 12px; left: 24px; right: 24px; font-size: 9px; color: #6b7280; border-top: 1px solid #d9dee3; padding-top: 5px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Efektivitas Genetic Algorithm</h1>
        <div class="meta muted">
            Warehouse Management System PT XYZ<br>
            Tahun laporan: <strong>{{ $year }}</strong> · Dicetak: <strong>{{ now()->format('d M Y H:i') }}</strong>
        </div>
    </div>

    <div class="note">
        Laporan ini merangkum performa Genetic Algorithm dalam menghasilkan rekomendasi lokasi put-away,
        meliputi kualitas fitness, waktu eksekusi, tingkat kepatuhan operator, serta perbandingan skenario
        penempatan aktual, acak, dan rekomendasi GA.
    </div>

    <h2>Ringkasan Utama</h2>
    <table class="summary-grid">
        <tr>
            <td><div class="label">Total GA Run</div><div class="value">{{ number_format($summary['total_ga']) }}</div></td>
            <td><div class="label">Average Fitness</div><div class="value">{{ $summary['avg_fitness'] }}</div></td>
            <td><div class="label">Best Fitness</div><div class="value">{{ $summary['best_fitness'] }}</div></td>
            <td><div class="label">Avg Execution</div><div class="value">{{ number_format($summary['avg_exec_ms']) }} ms</div></td>
        </tr>
        <tr>
            <td><div class="label">Follow GA Rate</div><div class="value">{{ $summary['compliance_pct'] }}%</div></td>
            <td><div class="label">Split Location</div><div class="value">{{ number_format($summary['split_location_count']) }}</div></td>
            <td><div class="label">Avg Location / SKU</div><div class="value">{{ $summary['avg_locations_per_sku'] }}</div></td>
            <td><div class="label">Rack Utilization</div><div class="value">{{ $summary['rack_utilization'] }}%</div></td>
        </tr>
    </table>

    <h2>Perbandingan Skenario Pengujian</h2>
    <table>
        <thead>
            <tr>
                <th>Skenario</th>
                <th class="text-center">Split Location</th>
                <th class="text-center">Avg Lokasi/SKU</th>
                <th class="text-center">Utilisasi Rak</th>
                <th class="text-center">Est. Waktu Put-Away</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($scenarioComparison as $sc)
            <tr>
                <td><strong>{{ $sc['label'] }}</strong></td>
                <td class="text-center">{{ $sc['split_count'] }}</td>
                <td class="text-center">{{ $sc['avg_loc'] }}</td>
                <td class="text-center">{{ $sc['utilization'] }}%</td>
                <td class="text-center">{{ $sc['putaway_min'] > 0 ? $sc['putaway_min'].' menit' : '-' }}</td>
                <td>{{ $sc['desc'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Riwayat GA Run Terakhir</h2>
    <table>
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th>DO</th>
                <th class="text-center">Fitness</th>
                <th class="text-center">Generasi</th>
                <th class="text-center">Waktu</th>
                <th class="text-center">Status</th>
                <th>Dijalankan Oleh</th>
                <th>Waktu Run</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gaRecords as $i => $ga)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $ga->inboundOrder->do_number ?? '-' }}</td>
                <td class="text-center">{{ $ga->fitness_score !== null ? number_format($ga->fitness_score, 4) : '-' }}</td>
                <td class="text-center">{{ number_format($ga->generations_run ?? 0) }}</td>
                <td class="text-center">{{ number_format($ga->execution_time_ms ?? 0) }} ms</td>
                <td class="text-center">
                    @php $statusClass = ['accepted'=>'badge-success','rejected'=>'badge-danger','pending_review'=>'badge-warning'][$ga->status] ?? 'badge-secondary'; @endphp
                    <span class="badge {{ $statusClass }}">{{ $ga->status }}</span>
                </td>
                <td>{{ $ga->generatedBy->name ?? '-' }}</td>
                <td>{{ $ga->generated_at?->format('d M Y H:i') ?? $ga->created_at->format('d M Y H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center muted">Belum ada data GA.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>
    <h2>Detail Rekomendasi GA</h2>
    <table>
        <thead>
            <tr>
                <th>DO</th>
                <th>SKU</th>
                <th>Item</th>
                <th>Cell</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Gene Fitness</th>
                <th class="text-right">FC Cap</th>
                <th class="text-right">FC Cat</th>
                <th class="text-right">FC Aff</th>
                <th class="text-right">FC Split</th>
            </tr>
        </thead>
        <tbody>
            @forelse($details as $d)
            <tr>
                <td>{{ $d->do_number }}</td>
                <td>{{ $d->sku }}</td>
                <td>{{ $d->item_name }}</td>
                <td>{{ $d->cell_code }}</td>
                <td class="text-right">{{ number_format($d->quantity) }}</td>
                <td class="text-right">{{ number_format($d->gene_fitness, 4) }}</td>
                <td class="text-right">{{ number_format($d->fc_cap_score, 4) }}</td>
                <td class="text-right">{{ number_format($d->fc_cat_score, 4) }}</td>
                <td class="text-right">{{ number_format($d->fc_aff_score, 4) }}</td>
                <td class="text-right">{{ number_format($d->fc_split_score, 4) }}</td>
            </tr>
            @empty
            <tr><td colspan="10" class="text-center muted">Belum ada detail rekomendasi GA.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Laporan dihasilkan otomatis oleh WMS PT XYZ. Data mengikuti kondisi sistem saat dokumen dicetak.
    </div>
</body>
</html>
