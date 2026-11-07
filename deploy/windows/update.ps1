<#
.SYNOPSIS
    Mise à jour de MeriMap sous Windows.

.DESCRIPTION
    1. Sauvegardez la base (le script le fait si mysqldump est trouvé).
    2. Remplacez les fichiers du projet par la nouvelle version, SANS supprimer .env ni storage\.
    3. Lancez :  powershell -ExecutionPolicy Bypass -File deploy\windows\update.ps1

    -SkipBackup : ne pas faire la sauvegarde automatique (si vous l'avez déjà faite).
#>
param([switch]$SkipBackup)
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Run($exe, [string[]]$arguments) {
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) { Write-Host "ERREUR : $exe $($arguments -join ' ')" -ForegroundColor Red; exit 1 }
}

if (-not (Test-Path .env)) { Write-Host 'ERREUR : .env introuvable, lancez plutôt install.ps1' -ForegroundColor Red; exit 1 }
Step 'Sauvegarde de la base'
if ($SkipBackup) {
    Write-Host 'Sauvegarde ignorée (-SkipBackup).' -ForegroundColor Yellow
} else {
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'backup.ps1')
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Mise à jour annulée : sauvegardez la base manuellement (phpMyAdmin > Exporter), puis relancez avec -SkipBackup.' -ForegroundColor Red
        exit 1
    }
}

Run php @('artisan', 'down')
try {
    Step 'Dépendances et interface'
    Run composer @('install', '--no-dev', '--optimize-autoloader', '--no-interaction')
    Run npm @('ci', '--no-audit', '--no-fund')
    Run npm @('run', 'build')

    Step 'Base de données et caches'
    Run php @('artisan', 'migrate', '--force')
    Run php @('artisan', 'db:seed', '--class=ReferenceSeeder', '--force')
    Run php @('artisan', 'optimize')
} finally {
    & php artisan up
}
Write-Host "`nMise à jour terminée." -ForegroundColor Green
