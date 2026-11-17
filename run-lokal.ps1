$ErrorActionPreference = "Continue"

$storageDir = Join-Path $PSScriptRoot "storage"
if (-not (Test-Path $storageDir)) {
    New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
}

$tunnelFile = Join-Path $storageDir "tunnel_url.txt"
$localIpFile = Join-Path $storageDir "local_ip.txt"

# Hapus file tunnel agar QR code memakai IP Jaringan Lokal (Wi-Fi/LAN)
if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }

# Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
$lanIp = $null
try {
    $netIps = Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*','vEthernet*' -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(169\.254|127\.)' }
    if ($netIps) {
        $gwRoute = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue | Sort-Object RouteMetric | Select-Object -First 1
        if ($gwRoute) {
            $gwIp = $netIps | Where-Object { $_.InterfaceIndex -eq $gwRoute.InterfaceIndex } | Select-Object -First 1
            if ($gwIp) { $lanIp = $gwIp.IPAddress }
        }
        if (-not $lanIp) {
            $lanIp = ($netIps | Select-Object -First 1).IPAddress
        }
    }
} catch {}

if (-not $lanIp) { $lanIp = "127.0.0.1" }
Set-Content -Path $localIpFile -Value $lanIp -NoNewline -Force

Write-Host "===================================================================" -ForegroundColor Cyan
Write-Host "       PHOTOBOOTH TIK - MODE JARINGAN LOKAL (OFFLINE / WI-FI)      " -ForegroundColor Cyan
Write-Host "===================================================================" -ForegroundColor Cyan
Write-Host ""

# Deteksi lokasi folder XAMPP
$xamppCandidates = @("d:\Xampp", "c:\xampp")
$xamppDir = $null
foreach ($c in $xamppCandidates) {
    if (Test-Path $c) {
        $xamppDir = $c
        break
    }
}

# 1. Jalankan MySQL (mysqld.exe) jika belum aktif
$mysqlPort = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if (-not $mysqlPort) {
    Write-Host "[1/4] Menyalakan MySQL (mysqld.exe)..." -ForegroundColor Yellow
    $mysqlBat = if ($xamppDir) { Join-Path $xamppDir "mysql_start.bat" } else { $null }
    $mysqlExe = if ($xamppDir) { Join-Path $xamppDir "mysql\bin\mysqld.exe" } else { $null }
    $mysqlIni = if ($xamppDir) { Join-Path $xamppDir "mysql\bin\my.ini" } else { $null }

    if ($mysqlBat -and (Test-Path $mysqlBat)) {
        $wmi = [wmiclass]"Win32_Process"
        $cmd = "cmd.exe /c `"`"$mysqlBat`"`""
        $wmi.Create($cmd, $xamppDir, $null) | Out-Null
    } elseif ($mysqlExe -and (Test-Path $mysqlExe) -and (Test-Path $mysqlIni)) {
        $wmi = [wmiclass]"Win32_Process"
        $cmd = "`"$mysqlExe`" --defaults-file=`"$mysqlIni`" --standalone"
        $wmi.Create($cmd, $xamppDir, $null) | Out-Null
    }

    # Tunggu port 3306 siap
    $mysqlOk = $false
    for ($i = 0; $i -lt 10; $i++) {
        Start-Sleep -Seconds 1
        $test = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
        if ($test) { $mysqlOk = $true; break }
    }
    if ($mysqlOk) {
        Write-Host "   -> MySQL (mysqld.exe) BERHASIL AKTIF di port 3306." -ForegroundColor Green
    } else {
        Write-Host "   -> MySQL sedang booting..." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[1/4] MySQL (mysqld.exe) sudah aktif di port 3306." -ForegroundColor Green
}

# 2. Server aplikasi menggunakan Apache tersendiri pada port 8000.
# Apache port 80 tidak diperlukan untuk menjalankan photobooth.

# 3. Jalankan digiCamControl (CameraControl.exe) jika belum aktif
$dccProc = Get-Process -Name "CameraControl" -ErrorAction SilentlyContinue
if (-not $dccProc) {
    Write-Host "[3/4] Menyalakan digiCamControl (CameraControl.exe)..." -ForegroundColor Yellow
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
        $dccDir = Split-Path $dccFound
        Start-Process -FilePath $dccFound -WorkingDirectory $dccDir -WindowStyle Hidden
        Write-Host "   -> digiCamControl BERHASIL DIBUKA: $dccFound" -ForegroundColor Green
    } else {
        Write-Host "   -> digiCamControl tidak ditemukan di lokasi standar (lewati jika memakai webcam)." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[3/4] digiCamControl (CameraControl.exe) sudah aktif." -ForegroundColor Green
}

# 4. Jalankan Apache khusus photobooth (request kamera tidak menahan halaman lain).
try {
    & (Join-Path $PSScriptRoot 'scripts/start-booth-server.ps1') -XamppDir $xamppDir
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "===================================================================" -ForegroundColor Green
Write-Host "      PHOTOBOOTH TIK - MODE JARINGAN LOKAL SIAP DIGUNAKAN!         " -ForegroundColor Green
Write-Host "===================================================================" -ForegroundColor Green
Write-Host ""
Write-Host " [JARINGAN LOKAL - HP/TABLET] : http://${lanIp}:8000" -ForegroundColor Yellow
Write-Host " [LOCAL HOST - BILIK FOTO]   : http://localhost:8000" -ForegroundColor Cyan
Write-Host " [LOCAL HOST - KAMERA HUB]   : http://localhost:8000/admin/camera-hub" -ForegroundColor Magenta
Write-Host " [LOCAL HOST - DASHBOARD]    : http://localhost:8000/admin/dashboard" -ForegroundColor White
Write-Host " [LOCAL HOST - PHPMYADMIN]   : http://localhost/phpmyadmin" -ForegroundColor DarkGray
Write-Host ""
Write-Host "===================================================================" -ForegroundColor Green
Write-Host " STATUS SISTEM:" -ForegroundColor White
Write-Host "  * MySQL (Port 3306)      : RUNNING" -ForegroundColor Green
Write-Host "  * Apache  (0.0.0.0:8000) : RUNNING (Jaringan Lokal & Localhost)" -ForegroundColor Green
Write-Host "  * digiCamControl         : RUNNING" -ForegroundColor Green
Write-Host "===================================================================" -ForegroundColor Green
Write-Host ""
Write-Host " PETUNJUK PENGGUNAAN JARINGAN LOKAL:" -ForegroundColor White
Write-Host " 1. Sambungkan HP/Tablet pengunjung ke Wi-Fi yang sama dengan laptop ini." -ForegroundColor Cyan
Write-Host " 2. QR Code pada hasil foto OTOMATIS mengarah ke: http://${lanIp}:8000" -ForegroundColor Yellow
Write-Host " 3. Pengunjung dapat scan QR code untuk langsung melihat & mengunduh foto." -ForegroundColor White
Write-Host " 4. Admin dapat mengoperasikan Kamera Hub di laptop ini untuk siaran." -ForegroundColor White
Write-Host ""
Write-Host "Membuka halaman Kamera Hub Admin di browser..." -ForegroundColor DarkGray
Start-Process "http://localhost:8000/admin/camera-hub"
Write-Host ""
Write-Host "Layanan Photobooth sedang berjalan di latar belakang." -ForegroundColor Green
Write-Host "Tekan Enter untuk menutup jendela ini (layanan tetap aktif di background)..." -ForegroundColor White
Write-Host "Tip: Untuk mematikan semua layanan, jalankan matikan-semua.bat." -ForegroundColor Cyan
try {
    $null = Read-Host
} catch {}

