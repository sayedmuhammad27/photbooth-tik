Write-Host "===================================================================" -ForegroundColor Red
Write-Host "         PHOTOBOOTH TIK - MEMATIKAN SEMUA LAYANAN...               " -ForegroundColor Red
Write-Host "===================================================================" -ForegroundColor Red
Write-Host ""

# 1. Hentikan Cloudflare Tunnel
Write-Host "[1/5] Menghentikan Cloudflare Tunnel..." -ForegroundColor Yellow
Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
$tunnelFile = Join-Path $PSScriptRoot "storage\tunnel_url.txt"
if (Test-Path $tunnelFile) { Remove-Item $tunnelFile -Force -ErrorAction SilentlyContinue }
Write-Host "   -> Cloudflare Tunnel dihentikan." -ForegroundColor Green

# 2. Hentikan Laravel Artisan (Port 8000)
Write-Host "[2/5] Menghentikan Laravel Artisan (port 8000)..." -ForegroundColor Yellow
$laravelConn = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($laravelConn) {
    foreach ($c in $laravelConn) {
        if ($c.OwningProcess) {
            Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue
        }
    }
}
Write-Host "   -> Server Laravel dihentikan." -ForegroundColor Green

# 3. Hentikan digiCamControl (CameraControl.exe)
Write-Host "[3/5] Menghentikan digiCamControl..." -ForegroundColor Yellow
Get-Process -Name "CameraControl" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Write-Host "   -> digiCamControl ditutup." -ForegroundColor Green

# 4. Hentikan Apache (httpd.exe)
Write-Host "[4/5] Menghentikan Apache..." -ForegroundColor Yellow
Get-Process -Name "httpd" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Write-Host "   -> Apache dimatikan." -ForegroundColor Green

# 5. Hentikan MySQL (mysqld.exe)
Write-Host "[5/5] Menghentikan MySQL..." -ForegroundColor Yellow
$mysqlAdmin = "d:\Xampp\mysql\bin\mysqladmin.exe"
if (Test-Path $mysqlAdmin) {
    & $mysqlAdmin -u root shutdown 2>$null
    Start-Sleep -Seconds 1
}
# Jika masih ada proses mysqld yang tersisa, hentikan
Get-Process -Name "mysqld" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Write-Host "   -> MySQL dimatikan." -ForegroundColor Green

Write-Host ""
Write-Host "===================================================================" -ForegroundColor Green
Write-Host "         SEMUA LAYANAN PHOTOBOOTH TELAH BERHASIL DIMATIKAN!        " -ForegroundColor Green
Write-Host "===================================================================" -ForegroundColor Green
Write-Host "RAM dan port komputer Anda telah bersih kembali." -ForegroundColor White
Write-Host ""
