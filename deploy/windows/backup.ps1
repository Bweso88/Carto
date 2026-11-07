<#
.SYNOPSIS
    Sauvegarde la base MeriMap dans le dossier backups\ (30 derniers jours conservés).

.DESCRIPTION
    Manuel :      powershell -ExecutionPolicy Bypass -File deploy\windows\backup.ps1
    Chaque nuit : voir docs\INSTALLATION-WINDOWS.md (tâche planifiée).
#>
param([int]$KeepDays = 30)
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")

if (-not (Test-Path .env)) { Write-Host 'ERREUR : .env introuvable.' -ForegroundColor Red; exit 1 }
$envFile = Get-Content .env -Encoding UTF8
function EnvValue($key) { (($envFile | Where-Object { $_ -match "^$key=" }) -replace "^$key=", '' -replace '^"|"$', '') }

$dump = Get-Command mysqldump -ErrorAction SilentlyContinue
$dumpExe = if ($dump) { $dump.Source } else {
    (Get-ChildItem 'C:\wamp64\bin\mysql\*\bin\mysqldump.exe', 'C:\wamp64\bin\mariadb\*\bin\mysqldump.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1).FullName
}
if (-not $dumpExe) { Write-Host 'ERREUR : mysqldump introuvable.' -ForegroundColor Red; exit 1 }

$dir = Join-Path (Get-Location) 'backups'
New-Item -ItemType Directory -Force -Path $dir | Out-Null
$file = Join-Path $dir "merimap-$(Get-Date -Format 'yyyyMMdd-HHmm').sql"

& $dumpExe --single-transaction -h (EnvValue 'DB_HOST') -P (EnvValue 'DB_PORT') -u (EnvValue 'DB_USERNAME') "--password=$(EnvValue 'DB_PASSWORD')" (EnvValue 'DB_DATABASE') "--result-file=$file"
if ($LASTEXITCODE -ne 0) { Write-Host 'ERREUR : la sauvegarde a échoué.' -ForegroundColor Red; exit 1 }

Get-ChildItem $dir -Filter 'merimap-*.sql' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) } | Remove-Item
Write-Host "Sauvegarde : $file"
