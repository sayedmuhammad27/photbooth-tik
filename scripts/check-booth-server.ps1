param([int]$Port = 8017)
$ErrorActionPreference = 'Stop'
$projectDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$configFile = Join-Path $projectDir "storage\app\runtime\httpd-$Port.conf"
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    throw "Port uji $Port sedang dipakai. Pilih -Port yang kosong."
}
$probeName = '__booth_probe_' + [guid]::NewGuid().ToString('N') + '.php'
$probeFile = Join-Path $projectDir "public\$probeName"
$probe = '<?php header("Content-Type: application/json"); if (isset($_GET["slow"])) { usleep(700000); } echo json_encode(["curl" => extension_loaded("curl"), "gd" => extension_loaded("gd"), "sapi" => PHP_SAPI]);'
$client = $null
try {
    [IO.File]::WriteAllText($probeFile, $probe, [Text.UTF8Encoding]::new($false))
    & (Join-Path $PSScriptRoot 'start-booth-server.ps1') -Port $Port -BindAddress 127.0.0.1
    Add-Type -AssemblyName System.Net.Http
    $client = [System.Net.Http.HttpClient]::new()
    $client.Timeout = [TimeSpan]::FromSeconds(10)
    $url = "http://127.0.0.1:$Port/$probeName"
    $status = $client.GetStringAsync($url).GetAwaiter().GetResult() | ConvertFrom-Json
    if (-not $status.curl -or -not $status.gd -or $status.sapi -ne 'apache2handler') {
        throw "PHP Apache belum siap: $($status | ConvertTo-Json -Compress)"
    }
    $clock = [Diagnostics.Stopwatch]::StartNew()
    $first = $client.GetStringAsync($url + '?slow=1')
    $second = $client.GetStringAsync($url + '?slow=1')
    $first.GetAwaiter().GetResult() | Out-Null
    $second.GetAwaiter().GetResult() | Out-Null
    $clock.Stop()
    if ($clock.ElapsedMilliseconds -ge 1300) { throw 'Dua request 700 ms tidak berjalan bersamaan.' }
    Write-Host "PASS: dua request PHP 700 ms selesai dalam $($clock.ElapsedMilliseconds) ms; cURL dan GD aktif."
} finally {
    if ($client) { $client.Dispose() }
    # Only stop this isolated test instance, never the event server on port 8000.
    $expected = $configFile.Replace('\', '/')
    $owned = @(Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" | Where-Object {
        $_.CommandLine -and $_.CommandLine.Replace('\', '/').Contains($expected)
    })
    $owned | Sort-Object CreationDate | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    if (Test-Path -LiteralPath $probeFile) { Remove-Item -LiteralPath $probeFile -Force }
}
