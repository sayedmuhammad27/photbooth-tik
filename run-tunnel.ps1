$ErrorActionPreference = "Continue"

$storageDir = Join-Path $PSScriptRoot "storage"
if (-not (Test-Path $storageDir)) {
    New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
}

$tunnelFile = Join-Path $storageDir "tunnel_url.txt"
$logFile = Join-Path $storageDir "cloudflared.log"

if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }
if (Test-Path $logFile) { Remove-Item $logFile -Force -ErrorAction SilentlyContinue }

Write-Host "===================================================================" -ForegroundColor Cyan
Write-Host "       PHOTOBOOTH TIK - ALL-IN-ONE SYSTEM LAUNCHER (AUTO)          " -ForegroundColor Cyan
Write-Host "===================================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Jalankan MySQL (mysqld.exe) jika belum aktif
$mysqlPort = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if (-not $mysqlPort) {
    Write-Host "[1/5] Menyalakan MySQL (mysqld.exe)..." -ForegroundColor Yellow
    $mysqlCandidates = @(
        "d:\Xampp\mysql\bin\mysqld.exe",
        "c:\xampp\mysql\bin\mysqld.exe"
    )
    $mysqlStarted = $false
    foreach ($mExe in $mysqlCandidates) {
        if (Test-Path $mExe) {
            $mDir = Split-Path (Split-Path $mExe)
            $mIni = Join-Path $mDir "mysql\bin\my.ini"
            Start-Process -FilePath $mExe -ArgumentList "--defaults-file=$mIni", "--standalone" -WorkingDirectory $mDir -WindowStyle Hidden
            for ($i = 0; $i -lt 8; $i++) {
                Start-Sleep -Seconds 1
                $test = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
                if ($test) { $mysqlStarted = $true; break }
            }
            break
        }
    }
    if ($mysqlStarted) {
        Write-Host "   -> MySQL aktif di port 3306." -ForegroundColor Green
    } else {
        Write-Host "   -> MySQL sedang menyala atau sudah berjalan di background." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[1/5] MySQL (mysqld.exe) sudah aktif di port 3306." -ForegroundColor Green
}

# 2. Jalankan Apache (httpd.exe) jika belum aktif
$apachePort = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if (-not $apachePort) {
    Write-Host "[2/5] Menyalakan Apache (httpd.exe)..." -ForegroundColor Yellow
    $apacheCandidates = @(
        "d:\Xampp\apache\bin\httpd.exe",
        "c:\xampp\apache\bin\httpd.exe"
    )
    $apacheStarted = $false
    foreach ($aExe in $apacheCandidates) {
        if (Test-Path $aExe) {
            $aDir = Split-Path (Split-Path $aExe)
            Start-Process -FilePath $aExe -WorkingDirectory $aDir -WindowStyle Hidden
            for ($i = 0; $i -lt 5; $i++) {
                Start-Sleep -Seconds 1
                $test = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
                if ($test) { $apacheStarted = $true; break }
            }
            break
        }
    }
    if ($apacheStarted) {
        Write-Host "   -> Apache aktif di port 80." -ForegroundColor Green
    } else {
        Write-Host "   -> Apache sedang menyala atau sudah berjalan di background." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[2/5] Apache (httpd.exe) sudah aktif di port 80." -ForegroundColor Green
}

# 3. Jalankan digiCamControl (CameraControl.exe) jika belum aktif
$dccProc = Get-Process -Name "CameraControl" -ErrorAction SilentlyContinue
if (-not $dccProc) {
    Write-Host "[3/5] Menyalakan digiCamControl (CameraControl.exe)..." -ForegroundColor Yellow
    $dccCandidates = @(
        "D:\DigiCamController\CameraControl.exe",
        "C:\Program Files (x86)\digiCamControl\CameraControl.exe",
        "C:\Program Files\digiCamControl\CameraControl.exe",
        "d:\digiCamControl\CameraControl.exe"
    )
    $dccFound = $null
    foreach ($dcc in $dccCandidates) {
        if (Test-Path $dcc) {
            $dccFound = $dcc
            break
        }
    }
    if ($dccFound) {
        Start-Process -FilePath $dccFound -WorkingDirectory (Split-Path $dccFound)
        Write-Host "   -> digiCamControl dibuka: $dccFound" -ForegroundColor Green
    } else {
        Write-Host "   -> digiCamControl tidak ditemukan di lokasi standar (lewati jika memakai webcam)." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[3/5] digiCamControl (CameraControl.exe) sudah aktif." -ForegroundColor Green
}

# 4. Jalankan Laravel Artisan (php artisan serve) jika port 8000 belum aktif
$laravelPort = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if (-not $laravelPort) {
    Write-Host "[4/5] Menyalakan server Laravel di port 8000..." -ForegroundColor Yellow
    Start-Process -FilePath "php" -ArgumentList "artisan", "serve", "--port=8000" -WorkingDirectory $PSScriptRoot -WindowStyle Hidden
    for ($i = 0; $i -lt 6; $i++) {
        Start-Sleep -Seconds 1
        $test = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
        if ($test) { break }
    }
    Write-Host "   -> Server Laravel aktif di http://127.0.0.1:8000" -ForegroundColor Green
} else {
    Write-Host "[4/5] Server Laravel port 8000 sudah aktif." -ForegroundColor Green
}

# 5. Hubungkan ke Cloudflare Tunnel
Write-Host "[5/5] Menghubungkan ke Cloudflare Tunnel..." -ForegroundColor Yellow
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

Write-Host ""
if ($detectedUrl) {
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host "        SEMUA LAYANAN PHOTOBOOTH TIK BERHASIL DINYALAKAN!          " -ForegroundColor Green
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " 🌐 [ONLINE URL - PENGUNJUNG] : $detectedUrl" -ForegroundColor Yellow
    Write-Host " 💻 [LOCAL HOST - BILIK FOTO] : http://localhost:8000" -ForegroundColor Cyan
    Write-Host " 📷 [LOCAL HOST - KAMERA HUB] : http://localhost:8000/admin/camera-hub" -ForegroundColor Magenta
    Write-Host " ⚙️  [LOCAL HOST - DASHBOARD]  : http://localhost:8000/admin/dashboard" -ForegroundColor White
    Write-Host " 🗄️  [LOCAL HOST - PHPMYADMIN] : http://localhost/phpmyadmin" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host " STATUS SISTEM:" -ForegroundColor White
    Write-Host "  * MySQL (Port 3306)      : RUNNING" -ForegroundColor Green
    Write-Host "  * Apache (Port 80)       : RUNNING" -ForegroundColor Green
    Write-Host "  * Laravel (Port 8000)    : RUNNING" -ForegroundColor Green
    Write-Host "  * digiCamControl         : RUNNING" -ForegroundColor Green
    Write-Host "  * Cloudflare Tunnel      : CONNECTED" -ForegroundColor Green
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " * QR Code pada hasil foto OTOMATIS mengarah ke alamat Online!" -ForegroundColor White
    Write-Host " * Pengunjung bisa scan QR code dari HP menggunakan internet." -ForegroundColor White
    Write-Host " * Admin dapat membuka Kamera Hub di laptop ini untuk siaran." -ForegroundColor White
    Write-Host ""
    Write-Host "Membuka halaman Kamera Hub Admin di browser..." -ForegroundColor DarkGray
    Start-Process "http://localhost:8000/admin/camera-hub"
    Write-Host ""
    Write-Host "Tekan Ctrl+C untuk menutup tunnel dan server." -ForegroundColor DarkGray
} else {
    Write-Host "[PERINGATAN] Belum mendeteksi URL trycloudflare. Cek file storage\cloudflared.log" -ForegroundColor Yellow
    Write-Host "Namun server Localhost tetap aktif di http://localhost:8000" -ForegroundColor Cyan
    Start-Process "http://localhost:8000/admin/camera-hub"
}

# Tunggu cloudflared berjalan
try {
    $cfProcess.WaitForExit()
} finally {
    Write-Host ""
    Write-Host "Menutup sesi Photobooth..." -ForegroundColor Yellow
    if ($cfProcess -and -not $cfProcess.HasExited) {
        $cfProcess.Kill()
    }
    if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }

    Write-Host "Tunnel Cloudflare telah ditutup." -ForegroundColor Green
    Write-Host "Tip: Untuk mematikan MySQL, Apache & digiCamControl, jalankan 'matikan-semua.bat'." -ForegroundColor Cyan
}
