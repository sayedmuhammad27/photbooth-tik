$ErrorActionPreference = "Continue"

$storageDir = Join-Path $PSScriptRoot "storage"
if (-not (Test-Path $storageDir)) {
    New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
}

$tunnelFile = Join-Path $storageDir "tunnel_url.txt"
$logFile = Join-Path $storageDir "cloudflared.log"

if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }
if (Test-Path $logFile) { Remove-Item $logFile -Force -ErrorAction SilentlyContinue }

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   PHOTOBOOTH TIK - CLOUDFLARE TUNNEL (AUTO DETECT QR)   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Cek apakah server Laravel port 8000 sudah jalan
$tcpTest = Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue
if (-not $tcpTest) {
    Write-Host "[1/2] Menyalakan server Laravel di port 8000..." -ForegroundColor Green
    Start-Process -FilePath "php" -ArgumentList "artisan", "serve", "--port=8000" -WindowStyle Hidden
    Start-Sleep -Seconds 2
} else {
    Write-Host "[1/2] Server Laravel port 8000 sudah aktif." -ForegroundColor Green
}

# 2. Jalankan cloudflared
Write-Host "[2/2] Menghubungkan ke Cloudflare..." -ForegroundColor Green
$cfProcess = Start-Process -FilePath ".\cloudflared.exe" -ArgumentList "tunnel", "--url", "http://127.0.0.1:8000" -NoNewWindow -PassThru -RedirectStandardError $logFile

$detectedUrl = $null
for ($i = 0; $i -lt 35; $i++) {
    Start-Sleep -Seconds 1
    if (Test-Path $logFile) {
        $logContent = Get-Content $logFile -Raw -ErrorAction SilentlyContinue
        if ($logContent -match '(https:\/\/[a-z0-9-]+\.trycloudflare\.com)') {
            $detectedUrl = $matches[1]
            Set-Content -Path $tunnelFile -Value $detectedUrl -NoNewline -Force
            break
        }
    }
}

if ($detectedUrl) {
    Write-Host ""
    Write-Host "==========================================================" -ForegroundColor Green
    Write-Host " CLOUDFLARE TUNNEL AKTIF DENGAN SUKSES!" -ForegroundColor Green
    Write-Host " URL: $detectedUrl" -ForegroundColor Yellow
    Write-Host "==========================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " * QR Code pada hasil foto OTOMATIS mengarah ke alamat ini!" -ForegroundColor White
    Write-Host " * Pengunjung bisa scan QR code dari HP menggunakan internet." -ForegroundColor White
    Write-Host " * Buka URL di atas pada browser untuk mulai sesi photobooth." -ForegroundColor White
    Write-Host ""
    Write-Host "Tekan Ctrl+C untuk menutup tunnel." -ForegroundColor DarkGray
} else {
    Write-Host "[PERINGATAN] Belum mendeteksi URL trycloudflare. Cek file storage\cloudflared.log" -ForegroundColor Yellow
}

# Tunggu cloudflared berjalan
$cfProcess.WaitForExit()

# Saat ditutup, bersihkan file tunnel
if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }
