param(
    [string]$XamppDir,
    [ValidateRange(1024, 65535)][int]$Port = 8000,
    [ValidateSet('0.0.0.0', '127.0.0.1')][string]$BindAddress = '0.0.0.0',
    [switch]$CheckOnly
)

$ErrorActionPreference = 'Stop'
$projectDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $XamppDir) {
    $XamppDir = @('D:\Xampp', 'C:\xampp') | Where-Object {
        Test-Path -LiteralPath (Join-Path $_ 'apache\bin\httpd.exe')
    } | Select-Object -First 1
}
if (-not $XamppDir) { throw 'XAMPP tidak ditemukan. Gunakan -XamppDir dengan lokasi XAMPP.' }
$XamppDir = (Resolve-Path -LiteralPath $XamppDir).Path
$httpd = Join-Path $XamppDir 'apache\bin\httpd.exe'
$runtimeDir = Join-Path $projectDir 'storage\app\runtime'
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
$configFile = Join-Path $runtimeDir "httpd-$Port.conf"

# A private Apache config avoids changing the user's global XAMPP installation.
$apachePath = (Join-Path $XamppDir 'apache').Replace('\', '/')
$phpPath = (Join-Path $XamppDir 'php').Replace('\', '/')
$publicPath = (Join-Path $projectDir 'public').Replace('\', '/')
$runtimePath = $runtimeDir.Replace('\', '/')
$loadFiles = @('libcrypto-3-x64.dll', 'libssl-3-x64.dll', 'nghttp2.dll', 'libssh2.dll', 'brotlicommon.dll', 'brotlidec.dll', 'php8ts.dll', 'libpq.dll', 'libsqlite3.dll') | ForEach-Object {
    if (Test-Path -LiteralPath (Join-Path $XamppDir "php\$_")) { 'LoadFile "' + $phpPath + '/' + $_ + '"' }
}
$config = @"
ServerRoot "$apachePath"
Listen ${BindAddress}:$Port
ServerName localhost:$Port
PidFile "$runtimePath/httpd-$Port.pid"
ErrorLog "$runtimePath/httpd-$Port-error.log"
LogLevel warn
ServerTokens Prod
ServerSignature Off
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
$($loadFiles -join "`n")
LoadModule php_module "$phpPath/php8apache2_4.dll"
PHPIniDir "$phpPath"
TypesConfig conf/mime.types
DirectoryIndex index.php
DocumentRoot "$publicPath"
ThreadsPerChild 64
MaxConnectionsPerChild 0
KeepAlive On
KeepAliveTimeout 2
Timeout 120
EnableSendfile Off
AcceptFilter http none
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$publicPath">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<FilesMatch "\.(css|js|woff2?|ttf|otf|png|jpe?g|svg|ico)$">
    Header set Cache-Control "public, max-age=86400"
</FilesMatch>
"@
[IO.File]::WriteAllText($configFile, $config, [Text.UTF8Encoding]::new($false))
& $httpd -t -f $configFile
if ($LASTEXITCODE -ne 0) { throw 'Konfigurasi Apache photobooth tidak valid.' }
if ($CheckOnly) { return }

$listeners = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
if ($listeners.Count) {
    # Never kill another app to claim its port. The operator can stop an old Artisan instance.
    $expectedConfig = $configFile.Replace('\', '/')
    $owned = $true
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
        if (-not $process.CommandLine -or -not $process.CommandLine.Replace('\', '/').Contains($expectedConfig)) {
            $owned = $false
        }
    }
    if ($owned) { Write-Host "Apache photobooth sudah aktif di port $Port."; return }
    throw "Port $Port sedang dipakai proses lain. Tutup server lama lalu jalankan kembali launcher."
}

$server = Start-Process -FilePath $httpd -ArgumentList @('-f', ('"' + $configFile + '"')) -WorkingDirectory (Join-Path $XamppDir 'apache') -WindowStyle Hidden -PassThru
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    Start-Sleep -Milliseconds 300
    $server.Refresh()
    if ($server.HasExited) { throw "Apache berhenti. Periksa $runtimePath/httpd-$Port-error.log" }
    if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
        Write-Host "Apache photobooth aktif: http://localhost:$Port (64 worker threads)." -ForegroundColor Green
        return
    }
}
throw "Apache belum siap. Periksa $runtimePath/httpd-$Port-error.log"
