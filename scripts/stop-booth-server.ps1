param([ValidateRange(1024, 65535)][int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$configFile = (Join-Path $projectDir "storage\app\runtime\httpd-$Port.conf").Replace('\', '/')
$owned = @(Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" | Where-Object {
    $_.CommandLine -and $_.CommandLine.Replace('\', '/').Contains($configFile)
})
$owned | Sort-Object CreationDate | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Write-Host "Apache photobooth port $Port dihentikan ($($owned.Count) proses)."
