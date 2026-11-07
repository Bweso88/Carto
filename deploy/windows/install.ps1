<#
.SYNOPSIS
    Première installation de MeriMap sous Windows (WampServer).

.DESCRIPTION
    À lancer depuis le dossier du projet (ex. C:\merimap), dans PowerShell :
        powershell -ExecutionPolicy Bypass -File deploy\windows\install.ps1

    Le script :
      1. vérifie PHP 8.3+, ses extensions, Composer et Node.js ;
      2. installe les dépendances et compile l'interface ;
      3. crée le fichier .env (questions sur la base de données) ;
      4. crée les tables, charge les référentiels et optimise ;
      5. crée le premier compte administrateur.

    Les réponses peuvent être données en paramètres (sinon elles sont demandées) :
        .\deploy\windows\install.ps1 -AppUrl http://merimap.intranet.local -DbName merimap `
            -DbUser merimap -AdminEmail dsi@exemple.cg
#>
param(
    [string]$AppUrl,
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPassword,
    [string]$AdminEmail,
    [string]$AdminPassword
)
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")
$Root = Get-Location

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Fail($text) { Write-Host "ERREUR : $text" -ForegroundColor Red; exit 1 }
function Run($exe, [string[]]$arguments) {
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) { Fail "échec de : $exe $($arguments -join ' ')" }
}

# Remplace (ou ajoute) une ligne CLE=valeur dans .env, y compris si elle est commentée
function Set-EnvValue([string]$key, [string]$value) {
    $file = Join-Path $Root '.env'
    $lines = [System.Collections.Generic.List[string]](Get-Content $file -Encoding UTF8)
    $index = $lines.FindIndex({ param($l) $l -match "^\s*#?\s*$key=" })
    if ($value -match '[\s#"]') { $value = '"' + ($value -replace '"', '\"') + '"' }
    if ($index -ge 0) { $lines[$index] = "$key=$value" } else { $lines.Add("$key=$value") }
    [System.IO.File]::WriteAllLines($file, $lines, (New-Object System.Text.UTF8Encoding $false))
}

# ---------------------------------------------------------------- 1. Vérifications
Step 'Vérification des outils'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Fail "php introuvable. Ajoutez le dossier de PHP 8.3 de WampServer au PATH (ex. C:\wamp64\bin\php\php8.3.x), puis rouvrez PowerShell."
}
$phpVersion = (& php -r 'echo PHP_VERSION;')
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.3.0') {
    Fail "PHP $phpVersion détecté : MeriMap nécessite PHP 8.3 ou plus."
}
Write-Host "PHP $phpVersion"

$modules = (& php -m) -join "`n"
$missing = @('pdo_mysql', 'mbstring', 'intl', 'zip', 'fileinfo', 'openssl', 'curl') |
    Where-Object { $modules -notmatch "(?im)^$_$" }
if ($missing) {
    Fail "extensions PHP manquantes : $($missing -join ', '). Activez-les dans le php.ini de la ligne de commande ($(& php -r 'echo php_ini_loaded_file();'))."
}

foreach ($tool in 'composer', 'npm') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Fail "$tool introuvable. Installez Composer (getcomposer.org) et Node.js LTS (nodejs.org), puis rouvrez PowerShell."
    }
}

# ---------------------------------------------------------------- 2. Configuration
Step 'Configuration (.env)'
if (Test-Path .env) {
    Write-Host '.env existe déjà : conservé tel quel.'
} else {
    Copy-Item .env.example .env
    $url = if ($AppUrl) { $AppUrl } else { Read-Host "Adresse du site (ex. http://merimap.intranet.local)" }
    $dbName = if ($DbName) { $DbName } else { Read-Host 'Nom de la base MySQL [merimap]' }
    if (-not $dbName) { $dbName = 'merimap' }
    $dbUser = if ($DbUser) { $DbUser } else { Read-Host 'Utilisateur MySQL [merimap]' }
    if (-not $dbUser) { $dbUser = 'merimap' }
    $dbPassPlain = $DbPassword
    if (-not $dbPassPlain) {
        $secure = Read-Host 'Mot de passe MySQL' -AsSecureString
        $dbPassPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
    }

    Set-EnvValue 'APP_ENV' 'production'
    Set-EnvValue 'APP_DEBUG' 'false'
    Set-EnvValue 'APP_URL' $url
    Set-EnvValue 'DB_CONNECTION' 'mysql'
    Set-EnvValue 'DB_HOST' '127.0.0.1'
    Set-EnvValue 'DB_PORT' '3306'
    Set-EnvValue 'DB_DATABASE' $dbName
    Set-EnvValue 'DB_USERNAME' $dbUser
    Set-EnvValue 'DB_PASSWORD' $dbPassPlain
    Set-EnvValue 'SESSION_SECURE_COOKIE' ($(if ($url -like 'https://*') { 'true' } else { 'false' }))
    Set-EnvValue 'MERIMAP_REGISTRATION' 'false'
    Write-Host '.env créé.'
}

# ---------------------------------------------------------------- 3. Dépendances
Step 'Dépendances PHP (Composer)'
Run composer @('install', '--no-dev', '--optimize-autoloader', '--no-interaction')

if (-not ((Get-Content .env -Encoding UTF8) -match '^APP_KEY=.+')) {
    Step 'Clé de chiffrement'
    Run php @('artisan', 'key:generate', '--force')
}

Step "Compilation de l'interface (Node.js)"
Run npm @('ci', '--no-audit', '--no-fund')
Run npm @('run', 'build')

# ---------------------------------------------------------------- 4. Base de données
Step 'Base de données'
Run php @('artisan', 'migrate', '--force')
Run php @('artisan', 'db:seed', '--class=ReferenceSeeder', '--force')
Run php @('artisan', 'optimize')

# ---------------------------------------------------------------- 5. Administrateur
Step 'Premier compte administrateur'
$adminEmail = if ($AdminEmail) { $AdminEmail } else { Read-Host 'E-mail de l''administrateur (laisser vide pour plus tard)' }
if ($adminEmail) {
    $userArgs = @('artisan', 'merimap:user', $adminEmail, '--role=admin', '--name=Administrateur')
    if ($AdminPassword) { $userArgs += "--password=$AdminPassword" }
    Run php $userArgs
}

Write-Host "`nMeriMap est installé dans $Root" -ForegroundColor Green
Write-Host "Dernière étape : déclarer le site dans WampServer (racine = $Root\public). Voir docs\INSTALLATION-WINDOWS.md."
