param(
    [string]$BindHost = "127.0.0.1",
    [int]$Port = 8001,
    [int]$TimeoutSeconds = 15
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$gaEngineDir = Join-Path $repoRoot "ga-engine"
$pythonExe = Join-Path $repoRoot ".venv\Scripts\python.exe"
$healthUrl = "http://$BindHost`:$Port/"

if (-not (Test-Path $gaEngineDir)) {
    Write-Host "[ERROR] Folder ga-engine tidak ditemukan: $gaEngineDir" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $pythonExe)) {
    Write-Host "[ERROR] Python virtualenv tidak ditemukan: $pythonExe" -ForegroundColor Red
    Write-Host "        Pastikan .venv sudah dibuat di root project." -ForegroundColor Yellow
    exit 1
}

$listener = Get-NetTCPConnection -LocalAddress $BindHost -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($listener) {
    try {
        $health = Invoke-RestMethod -Uri $healthUrl -Method Get -TimeoutSec 2 -ErrorAction Stop
        if ($health.status -eq "ok") {
            Write-Host "[OK] GA Engine sudah berjalan di $healthUrl (PID $($listener.OwningProcess))." -ForegroundColor Green
            exit 0
        }
    } catch {
        # Port aktif tapi bukan service GA sehat
    }

    Write-Host "[WARN] Port $Port sedang dipakai PID $($listener.OwningProcess)." -ForegroundColor Yellow
    Write-Host "       Stop proses tersebut terlebih dahulu atau ganti port." -ForegroundColor Yellow
    exit 1
}

Write-Host "[INFO] Menjalankan GA Engine..." -ForegroundColor Cyan
$args = @("-m", "uvicorn", "main:app", "--host", $BindHost, "--port", $Port.ToString())
$proc = Start-Process -FilePath $pythonExe -ArgumentList $args -WorkingDirectory $gaEngineDir -PassThru

$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
while ((Get-Date) -lt $deadline) {
    try {
        $health = Invoke-RestMethod -Uri $healthUrl -Method Get -TimeoutSec 2 -ErrorAction Stop
        if ($health.status -eq "ok") {
            Write-Host "[OK] GA Engine aktif di $healthUrl (PID $($proc.Id))." -ForegroundColor Green
            Write-Host "     Anda bisa langsung klik tombol Jalankan GA di aplikasi." -ForegroundColor Green
            exit 0
        }
    } catch {
        # Tunggu service siap
    }

    [System.Threading.Thread]::Sleep(500)
}

Write-Host "[ERROR] Proses sudah jalan (PID $($proc.Id)) tapi health check gagal > $TimeoutSeconds detik." -ForegroundColor Red
Write-Host "        Jalankan manual di folder ga-engine untuk melihat log detail." -ForegroundColor Yellow
exit 1
