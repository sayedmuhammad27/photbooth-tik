$ErrorActionPreference = "Continue"

$storageDir = Join-Path $PSScriptRoot "storage"
if (-not (Test-Path $storageDir)) {
    New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
}

$tunnelFile = Join-Path $storageDir "tunnel_url.txt"
$logFile = Join-Path $storageDir "cloudflared.log"
$localIpFile = Join-Path $storageDir "local_ip.txt"

if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }
if (Test-Path $logFile) { Remove-Item $logFile -Force -ErrorAction SilentlyContinue }

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
Write-Host "       PHOTOBOOTH TIK - ALL-IN-ONE SYSTEM LAUNCHER (AUTO)          " -ForegroundColor Cyan
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

# 1. Jalankan MySQL (mysqld.exe) jika belum aktif. Dilewati bila aplikasi memakai SQLite.
$envFile = Join-Path $PSScriptRoot ".env"
$usesSqlite = (Test-Path $envFile) -and (Select-String -Path $envFile -Pattern '^\s*DB_CONNECTION\s*=\s*sqlite' -Quiet)
$mysqlPort = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if ($usesSqlite) {
    Write-Host "[1/5] Database SQLite dipakai, MySQL tidak perlu dinyalakan." -ForegroundColor Green
} elseif (-not $mysqlPort) {
    Write-Host "[1/5] Menyalakan MySQL (mysqld.exe)..." -ForegroundColor Yellow
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
    Write-Host "[1/5] MySQL (mysqld.exe) sudah aktif di port 3306." -ForegroundColor Green
}

# 2. Server aplikasi menggunakan Apache tersendiri pada port 8000.
# Apache port 80 tidak diperlukan untuk menjalankan photobooth.

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
        $dccDir = Split-Path $dccFound
        Start-Process -FilePath $dccFound -WorkingDirectory $dccDir -WindowStyle Hidden
        Write-Host "   -> digiCamControl BERHASIL DIBUKA: $dccFound" -ForegroundColor Green
    } else {
        Write-Host "   -> digiCamControl tidak ditemukan di lokasi standar (lewati jika memakai webcam)." -ForegroundColor DarkGray
    }
} else {
    Write-Host "[3/5] digiCamControl (CameraControl.exe) sudah aktif." -ForegroundColor Green
}

# 4. Jalankan Apache khusus photobooth (request kamera tidak menahan halaman lain).
try {
    & (Join-Path $PSScriptRoot 'scripts/start-booth-server.ps1') -XamppDir $xamppDir
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# 5. Hubungkan ke Cloudflare Tunnel
Write-Host "[5/5] Menghubungkan ke Cloudflare Tunnel..." -ForegroundColor Yellow
# Cari cloudflared: folder project dulu, lalu PATH, lalu lokasi instalasi standar.
$cfExe = $null
$cfCandidates = @(
    (Join-Path $PSScriptRoot "cloudflared.exe"),
    ((Get-Command cloudflared -ErrorAction SilentlyContinue | Select-Object -First 1).Source),
    "C:\Program Files (x86)\cloudflared\cloudflared.exe",
    "C:\Program Files\cloudflared\cloudflared.exe"
)
foreach ($c in $cfCandidates) {
    if ($c -and (Test-Path -LiteralPath $c)) { $cfExe = $c; break }
}
if (-not $cfExe) {
    Write-Host "   -> cloudflared tidak ditemukan. Pasang dengan: winget install Cloudflare.cloudflared" -ForegroundColor Red
    Write-Host "   -> Server lokal tetap aktif: http://${lanIp}:8000 dan http://localhost:8000" -ForegroundColor Cyan
    exit 1
}
Write-Host "   -> Memakai $cfExe" -ForegroundColor DarkGray

# Hentikan proses cloudflared lama jika ada agar tidak terjadi konflik port / tunnel
$oldCf = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
if ($oldCf) {
    Write-Host "   -> Menghentikan instance cloudflared lama..." -ForegroundColor DarkGray
    $oldCf | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
}

$cfProcess = Start-Process -FilePath $cfExe -ArgumentList "tunnel", "--url", "http://127.0.0.1:8000" -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru -RedirectStandardError $logFile

$detectedUrl = $null
for ($i = 0; $i -lt 35; $i++) {
    Start-Sleep -Seconds 1
    if (Test-Path $logFile) {
        $logContent = Get-Content $logFile -Raw -ErrorAction SilentlyContinue
        if ($logContent) {
            $allMatches = [regex]::Matches($logContent, 'https:\/\/[a-z0-9-]+\.trycloudflare\.com')
            if ($allMatches.Count -gt 0) {
                $detectedUrl = $allMatches[$allMatches.Count - 1].Value
                Set-Content -Path $tunnelFile -Value $detectedUrl -NoNewline -Force
                break
            }
        }
    }
}

Write-Host ""
if ($detectedUrl) {
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host "        SEMUA LAYANAN PHOTOBOOTH TIK BERHASIL DINYALAKAN!          " -ForegroundColor Green
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " [ONLINE URL - PENGUNJUNG]    : $detectedUrl" -ForegroundColor Yellow
    Write-Host " [JARINGAN LOKAL - WI-FI/LAN] : http://${lanIp}:8000" -ForegroundColor Cyan
    Write-Host " [LOCAL HOST - BILIK FOTO]    : http://localhost:8000" -ForegroundColor White
    Write-Host " [LOCAL HOST - KAMERA HUB]    : http://localhost:8000/admin/camera-hub" -ForegroundColor Magenta
    Write-Host " [LOCAL HOST - DASHBOARD]     : http://localhost:8000/admin/dashboard" -ForegroundColor DarkGray
    Write-Host " [LOCAL HOST - PHPMYADMIN]    : http://localhost/phpmyadmin" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host " STATUS SISTEM:" -ForegroundColor White
    Write-Host "  * MySQL (Port 3306)      : RUNNING" -ForegroundColor Green
    Write-Host "  * Apache  (0.0.0.0:8000) : RUNNING (Localhost + Jaringan Lokal)" -ForegroundColor Green
    Write-Host "  * digiCamControl         : RUNNING" -ForegroundColor Green
    Write-Host "  * Cloudflare Tunnel      : CONNECTED" -ForegroundColor Green
    Write-Host "===================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " * QR Code pada hasil foto OTOMATIS mengarah ke alamat Online!" -ForegroundColor White
    Write-Host " * Pengunjung bisa scan QR code dari HP menggunakan internet." -ForegroundColor White
    Write-Host " * Pengunjung / Operator juga bisa akses langsung lewat Wi-Fi: http://${lanIp}:8000" -ForegroundColor White
    Write-Host " * Admin dapat membuka Kamera Hub di laptop ini untuk siaran." -ForegroundColor White
    Write-Host ""
    Write-Host "Membuka halaman Kamera Hub Admin di browser..." -ForegroundColor DarkGray
    Start-Process "http://localhost:8000/admin/camera-hub"
    Write-Host ""
    Write-Host "Tekan Ctrl+C untuk menutup tunnel." -ForegroundColor DarkGray
} else {
    Write-Host "[PERINGATAN] Belum mendeteksi URL trycloudflare. Cek file storage\cloudflared.log" -ForegroundColor Yellow
    Write-Host "Namun server Jaringan Lokal & Localhost tetap aktif:" -ForegroundColor Cyan
    Write-Host " -> http://${lanIp}:8000 (Jaringan Lokal / Wi-Fi)" -ForegroundColor Yellow
    Write-Host " -> http://localhost:8000 (Laptop ini)" -ForegroundColor Cyan
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
    Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }

    Write-Host "Tunnel Cloudflare telah ditutup." -ForegroundColor Green
    Write-Host "Tip: Untuk mematikan MySQL, Apache & digiCamControl, jalankan matikan-semua.bat." -ForegroundColor Cyan
}
