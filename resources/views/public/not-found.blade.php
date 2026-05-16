<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Kode Tidak Ditemukan</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    * { -webkit-tap-highlight-color: transparent; }
    body {
        background: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #1a1a2e; min-height: 100vh;
    }
    .topbar {
        background: linear-gradient(135deg, #1a2332 0%, #2d3a4f 100%);
        color: #fff; padding: 14px 16px;
        display: flex; align-items: center; gap: 10px;
        position: sticky; top: 0; z-index: 100;
        box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .topbar-logo {
        width: 32px; height: 32px; background: #28a745;
        border-radius: 8px; display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: 14px; flex-shrink: 0;
    }
    .topbar-title { font-size: 13px; font-weight: 700; line-height: 1.2; }
    .topbar-sub   { font-size: 11px; opacity: .7; }

    .not-found-card {
        background: #fff; margin: 20px 12px;
        border-radius: 14px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
        padding: 36px 20px; text-align: center;
    }
    .nf-icon  { font-size: 52px; color: #dee2e6; margin-bottom: 16px; }
    .nf-title { font-size: 18px; font-weight: 800; color: #495057; margin-bottom: 8px; }
    .nf-code  {
        display: inline-block; background: #f8f9fa; border: 1px solid #dee2e6;
        border-radius: 8px; padding: 6px 14px; font-family: monospace;
        font-size: 16px; font-weight: 700; color: #343a40; margin-bottom: 12px;
    }
    .nf-body { font-size: 13px; color: #6c757d; line-height: 1.6; }

    .tip-box {
        margin: 0 12px 16px;
        background: #e8f4fd; border-radius: 10px;
        padding: 12px 14px; font-size: 12px; color: #1565c0;
        display: flex; align-items: flex-start; gap: 8px;
    }
    .page-footer {
        text-align: center; padding: 20px 16px;
        font-size: 11px; color: #adb5bd;
    }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-logo">W</div>
    <div>
        <div class="topbar-title">WMS Avian</div>
        <div class="topbar-sub">QR Tidak Dikenali</div>
    </div>
</div>

<div class="not-found-card">
    <div class="nf-icon"><i class="fas fa-question-circle"></i></div>
    <div class="nf-title">Kode Tidak Ditemukan</div>
    <div class="nf-code">{{ $code }}</div>
    <div class="nf-body">
        Kode ini tidak dikenali sebagai sel gudang maupun barang dalam sistem.<br><br>
        Pastikan Anda memindai QR yang benar:<br>
        <strong>• QR Rak</strong> — terpasang di label rak fisik (contoh: <strong>2-A</strong>)<br>
        <strong>• QR Barang</strong> — terpasang di kardus/item (untuk info stok)
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-info-circle" style="flex-shrink:0;font-size:16px;margin-top:1px;"></i>
    <span>Jika masalah berlanjut, hubungi supervisor gudang untuk pengecekan label QR.</span>
</div>

<div class="page-footer">
    <i class="fas fa-warehouse mr-1"></i> WMS Avian<br>
    <span style="font-size:10px;">{{ now()->format('d M Y, H:i') }} WIB</span>
</div>

</body>
</html>
