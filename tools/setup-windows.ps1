# =====================================================================
#  IFGF Church CMS - Windows developer toolchain bootstrap  (v2)
#  Work Package 0A, required items 1 and 2.
#
#  Run in an ELEVATED PowerShell:
#     Set-ExecutionPolicy -Scope Process Bypass -Force
#     .\tools\setup-windows.ps1
#
#  v2 changes (winget proved unreliable for PHP/Composer on 2026-08-08):
#   - PHP is downloaded directly from windows.php.net with URL probing,
#     because winget's PHP.PHP.8.3 manifest points at 8.3.32 which has
#     moved to the archives directory and now 404s.
#   - Composer uses the correct winget id GetComposer.Composer, with a
#     Composer-Setup.exe fallback.
#   - PATH is refreshed in-process, so STEP 2-4 work in the same run.
#   - Existing MySQL installs are discovered rather than reinstalled.
#
#  This script does NOT upgrade Laravel or any Composer package.
#  Dependency upgrades are Work Package 0B behind a separate gate.
# =====================================================================

[CmdletBinding()]
param(
    # PHP 8.3 is the only line satisfying BOTH Laravel 10 (supports 8.1-8.3)
    # and Laravel 13 (requires >=8.3). Newest 8.3 patch as of 2026-08-08.
    [string]$PhpBranch  = '8.3',
    [string[]]$PhpPatchCandidates = @('8.3.33','8.3.32','8.3.31','8.3.30'),
    [string]$PhpRoot    = 'C:\php\8.3',

    # laravel-mix 4 / webpack 4 will not build on modern Node.
    [string]$LegacyNodeVersion = '16.20.2',

    [switch]$SkipInstall,
    [switch]$SkipComposer,
    [switch]$SkipNpm
)

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'   # much faster Invoke-WebRequest
$repo = Split-Path -Parent $PSScriptRoot
Set-Location $repo

function Section($t) {
    Write-Host ''
    Write-Host ('=' * 70) -ForegroundColor DarkCyan
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ('=' * 70) -ForegroundColor DarkCyan
}
function Have($c) { $null -ne (Get-Command $c -ErrorAction SilentlyContinue) }
function Sync-Path {
    $env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' +
                [Environment]::GetEnvironmentVariable('Path','User')
}
function Add-MachinePath($dir) {
    $cur = [Environment]::GetEnvironmentVariable('Path','Machine')
    if ($cur -split ';' -notcontains $dir) {
        [Environment]::SetEnvironmentVariable('Path', "$cur;$dir", 'Machine')
        Write-Host "    PATH += $dir" -ForegroundColor Green
    }
    Sync-Path
}
function Test-Url($u) {
    try {
        $r = Invoke-WebRequest -Uri $u -Method Head -UseBasicParsing -TimeoutSec 20
        return $r.StatusCode -eq 200
    } catch { return $false }
}

Section 'STEP 0  Pre-flight'
Write-Host "  Repo:      $repo"
Write-Host "  PowerShell $($PSVersionTable.PSVersion)"
Write-Host "  OS:        $([System.Environment]::OSVersion.VersionString)"
$isAdmin = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent() `
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
Write-Host "  Elevated:  $isAdmin"
if (-not $isAdmin) { Write-Warning 'NOT ELEVATED. Re-run as Administrator or installs will fail.' }
Sync-Path

# =====================================================================
if (-not $SkipInstall) {

Section 'STEP 1a  Visual C++ Redistributable (required by PHP)'
winget install --id Microsoft.VCRedist.2015+.x64 -e --silent `
    --accept-package-agreements --accept-source-agreements 2>&1 | Select-Object -Last 3

# ---------------------------------------------------------------------
Section "STEP 1b  PHP $PhpBranch  (direct download - winget manifest is stale)"

if (Have php) {
    Write-Host "  PHP already on PATH: $(php -r 'echo PHP_VERSION;')" -ForegroundColor DarkGray
} else {
    # Probe: current releases dir first, then archives; NTS then TS; vs16.
    $found = $null
    foreach ($v in $PhpPatchCandidates) {
        foreach ($dir in @('releases','releases/archives')) {
            foreach ($flavour in @("php-$v-nts-Win32-vs16-x64.zip", "php-$v-Win32-vs16-x64.zip")) {
                $u = "https://windows.php.net/downloads/$dir/$flavour"
                Write-Host "    probe $u" -ForegroundColor DarkGray
                if (Test-Url $u) { $found = $u; break }
            }
            if ($found) { break }
        }
        if ($found) { break }
    }

    if (-not $found) {
        Write-Warning 'Could not locate a PHP 8.3 Windows build automatically.'
        Write-Warning 'Open https://windows.php.net/download/ , grab the VS16 x64 Non Thread Safe zip,'
        Write-Warning "extract it to $PhpRoot , then re-run with -SkipInstall."
    } else {
        Write-Host "  Downloading $found" -ForegroundColor Yellow
        $zip = Join-Path $env:TEMP 'php-ifgf.zip'
        Invoke-WebRequest -Uri $found -OutFile $zip -UseBasicParsing
        New-Item -ItemType Directory -Force -Path $PhpRoot | Out-Null
        Expand-Archive -Path $zip -DestinationPath $PhpRoot -Force
        Remove-Item $zip -Force
        Write-Host "  Extracted to $PhpRoot" -ForegroundColor Green

        # ---- php.ini -------------------------------------------------
        # Delegated to fix-php-ini.ps1 so there is exactly one place that
        # knows how to write php.ini. It enumerates the DLLs that actually
        # exist rather than trusting a hardcoded wishlist, and it knows
        # opcache is a zend_extension.
        & (Join-Path $PSScriptRoot 'fix-php-ini.ps1') -PhpRoot $PhpRoot

        Add-MachinePath $PhpRoot
    }
}

# ---------------------------------------------------------------------
Section 'STEP 1c  Composer'
Sync-Path
if (Have composer) {
    Write-Host '  Composer already present.' -ForegroundColor DarkGray
} else {
    # Correct winget id is GetComposer.Composer (NOT Composer.Composer).
    winget install --id GetComposer.Composer -e --silent `
        --accept-package-agreements --accept-source-agreements 2>&1 | Select-Object -Last 3
    Sync-Path
    if (-not (Have composer)) {
        Write-Host '  winget path failed - using Composer-Setup.exe' -ForegroundColor Yellow
        $exe = Join-Path $env:TEMP 'Composer-Setup.exe'
        try {
            Invoke-WebRequest -Uri 'https://getcomposer.org/Composer-Setup.exe' -OutFile $exe -UseBasicParsing
            Write-Host '  Launching Composer installer - accept the prompts, it will detect PHP.' -ForegroundColor Yellow
            Start-Process -FilePath $exe -Wait
        } catch {
            Write-Warning "Composer download failed: $_"
            Write-Warning 'Install manually from https://getcomposer.org/download/'
        }
        Sync-Path
    }
}

# ---------------------------------------------------------------------
Section 'STEP 1d  MySQL 8.4 LTS'
Sync-Path
if (Have mysql) {
    Write-Host '  mysql already on PATH.' -ForegroundColor DarkGray
} else {
    # winget reported an existing install - find it rather than reinstall.
    $cand = Get-ChildItem 'C:\Program Files\MySQL' -Directory -ErrorAction SilentlyContinue |
            Where-Object { Test-Path (Join-Path $_.FullName 'bin\mysql.exe') } |
            Sort-Object Name -Descending
    if ($cand) {
        $bin = Join-Path $cand[0].FullName 'bin'
        Write-Host "  Found existing MySQL: $($cand[0].Name)" -ForegroundColor Green
        Add-MachinePath $bin
    } else {
        Write-Host '  No MySQL server found. Installing...' -ForegroundColor Yellow
        winget install --id Oracle.MySQL -e --silent `
            --accept-package-agreements --accept-source-agreements 2>&1 | Select-Object -Last 3
        Write-Host '  If MySQL Installer opens, choose Server 8.4 LTS.' -ForegroundColor Yellow
    }
}

# ---------------------------------------------------------------------
Section "STEP 1e  Node $LegacyNodeVersion via nvm-windows"
Sync-Path
if (-not (Have nvm)) {
    winget install --id CoreyButler.NVMforWindows -e --silent `
        --accept-package-agreements --accept-source-agreements 2>&1 | Select-Object -Last 3
    Sync-Path
}
if (Have nvm) {
    Write-Host "  nvm install $LegacyNodeVersion" -ForegroundColor Yellow
    & nvm install $LegacyNodeVersion  2>&1 | Select-Object -Last 5
    & nvm use     $LegacyNodeVersion  2>&1 | Select-Object -Last 3
    Sync-Path
} else {
    Write-Warning 'nvm still not on PATH. Close and reopen PowerShell, then re-run with -SkipInstall.'
}

} # end -not SkipInstall

# =====================================================================
Sync-Path
Section 'STEP 2  Required PHP extensions'

$required = @(
    'bcmath','ctype','curl','dom','exif','fileinfo','filter','gd','hash',
    'iconv','json','libxml','mbstring','openssl','pcre','pdo','pdo_mysql',
    'phar','session','simplexml','sodium','tokenizer','xml','xmlreader',
    'xmlwriter','zip','zlib'
)

if (Have php) {
    $loaded  = (php -m 2>$null) | ForEach-Object { $_.Trim().ToLower() }
    $missing = $required | Where-Object { $loaded -notcontains $_ }
    if ($missing.Count -eq 0) {
        Write-Host '  All required extensions loaded.' -ForegroundColor Green
    } else {
        Write-Host "  MISSING: $($missing -join ', ')" -ForegroundColor Red
        php --ini 2>$null | Select-String 'Loaded Configuration File'
    }
} else {
    Write-Host '  PHP not on PATH - cannot check extensions.' -ForegroundColor Red
}

# ---------------------------------------------------------------------
Section 'STEP 3  Install project dependencies FROM LOCKFILES (no updates)'

if (-not $SkipComposer -and (Have composer) -and (Have php)) {
    if (-not (Test-Path "$repo\.env")) {
        Copy-Item "$repo\.env.example" "$repo\.env"
        Write-Host '  Created .env from .env.example' -ForegroundColor Green
    }
    Write-Host '  composer validate --strict' -ForegroundColor Yellow
    composer validate --strict 2>&1 | Select-Object -Last 15

    Write-Host '  composer install (lockfile-exact)' -ForegroundColor Yellow
    # build.md TECHNICAL BASELINE 5: never mask incompatibility.
    composer install --no-interaction --prefer-dist 2>&1 | Select-Object -Last 40
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'composer install FAILED. Do NOT retry with --ignore-platform-reqs.'
        Write-Warning 'Paste the output back - documented Work Package 0A blocker.'
    }
    Write-Host '  composer audit' -ForegroundColor Yellow
    composer audit --no-interaction 2>&1 | Select-Object -Last 30
} elseif (-not $SkipComposer) {
    Write-Host '  PHP or Composer missing - skipped.' -ForegroundColor Red
}

if (-not $SkipNpm -and (Have node) -and (Have npm)) {
    Write-Host "  node $(node --version) / npm $(npm --version)" -ForegroundColor Yellow
    if ((node --version) -notmatch '^v16\.') {
        Write-Warning "Node $(node --version) will likely break laravel-mix 4 / webpack 4."
        Write-Warning "Run:  nvm use $LegacyNodeVersion"
    }
    Write-Host '  npm ci (lockfile-exact)' -ForegroundColor Yellow
    npm ci 2>&1 | Select-Object -Last 40
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'npm ci FAILED - Work Package 0A finding, and evidence for the WP 0B Vite decision.'
    }
} elseif (-not $SkipNpm) {
    Write-Host '  node/npm not on PATH - skipped.' -ForegroundColor Red
}

# ---------------------------------------------------------------------
Section 'STEP 4  VERSION REPORT  (paste everything below back)'

function Report($label, $exe, $args) {
    if (Have $exe) {
        $v = (& $exe $args 2>&1 | Select-Object -First 1)
        Write-Host ("  {0,-12} {1}" -f $label, $v) -ForegroundColor Green
    } else {
        Write-Host ("  {0,-12} NOT INSTALLED" -f $label) -ForegroundColor Red
    }
}
Report 'PHP'      'php'      '--version'
Report 'Composer' 'composer' '--version'
Report 'Node'     'node'     '--version'
Report 'npm'      'npm'      '--version'
Report 'MySQL'    'mysql'    '--version'
Report 'Git'      'git'      '--version'

Write-Host ''
Write-Host '  Project state:' -ForegroundColor Cyan
foreach ($p in @('vendor','node_modules','.env','composer.lock','package-lock.json')) {
    $ok = Test-Path "$repo\$p"
    Write-Host ("    {0,-20} {1}" -f $p, $(if ($ok) {'present'} else {'MISSING'})) `
        -ForegroundColor $(if ($ok) {'Green'} else {'Red'})
}

Write-Host ''
Write-Host '  Required-but-missing extensions:' -ForegroundColor Cyan
if (Have php) {
    $loaded  = (php -m 2>$null) | ForEach-Object { $_.Trim().ToLower() }
    $missing = $required | Where-Object { $loaded -notcontains $_ }
    Write-Host ('    ' + $(if ($missing) { $missing -join ', ' } else { 'none' }))
} else { Write-Host '    (php unavailable)' }

if ((Have php) -and (Test-Path "$repo\vendor\autoload.php")) {
    Write-Host ''
    Write-Host '  php artisan about' -ForegroundColor Cyan
    php artisan about 2>&1 | Select-Object -First 35
}

Section 'DONE'
Write-Host '  Paste the STEP 4 report back. See EXECUTION_PLAN.md for the session map.'
Write-Host ''
