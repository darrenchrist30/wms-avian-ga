param(
    [string]$BindHost = "127.0.0.1",
    [int]$Port = 8001
)

$listeners = Get-NetTCPConnection -LocalAddress $BindHost -LocalPort $Port -State Listen -ErrorAction SilentlyContinue

if (-not $listeners) {
    Write-Host "[INFO] Tidak ada proses listening di $BindHost`:$Port."
    exit 0
}

$pids = $listeners | Select-Object -ExpandProperty OwningProcess -Unique
$stopped = 0

foreach ($pid in $pids) {
    try {
        $proc = Get-Process -Id $pid -ErrorAction Stop
    } catch {
        Write-Host "[WARN] PID $pid tidak ditemukan saat proses stop." -ForegroundColor Yellow
        continue
    }

    if ($proc.ProcessName -notmatch "^python") {
        Write-Host "[WARN] PID $pid bukan python process ($($proc.ProcessName)). Lewati demi aman." -ForegroundColor Yellow
        continue
    }

    try {
        Stop-Process -Id $pid -Force -ErrorAction Stop
        Write-Host "[OK] GA Engine berhenti (PID $pid)." -ForegroundColor Green
        $stopped++
    } catch {
        Write-Host "[ERROR] Gagal stop PID ${pid}: $($_.Exception.Message)" -ForegroundColor Red
    }
}

if ($stopped -eq 0) {
    Write-Host "[WARN] Tidak ada proses GA yang berhasil dihentikan." -ForegroundColor Yellow
    exit 1
}

exit 0
