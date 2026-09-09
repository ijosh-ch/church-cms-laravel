<#
.SYNOPSIS
    Creates the IFGF PostgreSQL role and databases when the 'postgres' superuser password
    is unknown.

.DESCRIPTION
    Standard PostgreSQL recovery: pg_hba.conf is briefly switched to local 'trust' auth,
    the role and databases are created, and pg_hba.conf is restored immediately.

    WHAT THIS DOES NOT DO
    ---------------------
    It does NOT change, reset or read the 'postgres' superuser password. That password
    stays unknown and untouched — we only need to CREATE a role, not to learn an existing
    credential. This is deliberately the least invasive option that works.

    THE RISK WINDOW, STATED PLAINLY
    -------------------------------
    Between the two service restarts (a few seconds), any process on this machine can
    connect to PostgreSQL as any role without a password. Two things bound that:

      * pg_hba.conf keeps its host rules scoped to 127.0.0.1 and ::1, so despite
        listen_addresses = '*', a remote connection still matches no rule and is rejected.
      * The restore runs in a finally block, so it happens even if the SQL fails or you
        Ctrl-C the script.

    The original pg_hba.conf is backed up with a timestamp before anything is touched, and
    the script verifies the restore before exiting. If it ever cannot restore, it says so
    loudly and tells you which backup file to copy back by hand.

    REQUIRES ADMINISTRATOR - it edits a file under Program Files and restarts a service.

.EXAMPLE
    # In an ELEVATED PowerShell (right-click - Run as Administrator):
    powershell -ExecutionPolicy Bypass -File "D:\Users\Ian Joseph\Documents\GitHub\church-cms-laravel\tools\setup-postgres-no-superuser.ps1"
#>

[CmdletBinding()]
param(
    [string]$Password,
    [string]$PgBin = 'C:\Program Files\PostgreSQL\17\bin',
    [string]$DataDir = 'C:\Program Files\PostgreSQL\17\data',
    [string]$ServiceName = 'postgresql-x64-17'
)

$ErrorActionPreference = 'Stop'

# ── Preconditions ───────────────────────────────────────────────────────────────
$isAdmin = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent()
).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    throw "This script must run as Administrator - it edits pg_hba.conf under Program Files and restarts the $ServiceName service. Right-click PowerShell and choose 'Run as Administrator', then run it again."
}

$psql = Join-Path $PgBin 'psql.exe'
if (-not (Test-Path $psql))    { throw "psql not found at $psql. Pass -PgBin." }

$hba = Join-Path $DataDir 'pg_hba.conf'
if (-not (Test-Path $hba))     { throw "pg_hba.conf not found at $hba. Pass -DataDir." }

if (-not (Get-Service -Name $ServiceName -ErrorAction SilentlyContinue)) {
    throw "Service $ServiceName not found. Pass -ServiceName."
}

$envFile = Join-Path $env:USERPROFILE '.ifgf\postgres.env'
if (-not (Test-Path $envFile)) { throw "Credential file not found at $envFile." }

# ── Read the target names from the credential file ──────────────────────────────
$cfg = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
        $k, $v = $line.Split('=', 2)
        $cfg[$k.Trim()] = $v.Trim()
    }
}
$role   = if ($cfg['IFGF_PG_USERNAME'])      { $cfg['IFGF_PG_USERNAME'] }      else { 'ifgf' }
$dbLive = if ($cfg['IFGF_PG_DATABASE'])      { $cfg['IFGF_PG_DATABASE'] }      else { 'ifgf_cms' }
$dbTest = if ($cfg['IFGF_PG_TEST_DATABASE']) { $cfg['IFGF_PG_TEST_DATABASE'] } else { 'ifgf_cms_test' }

# ── Password for the NEW ifgf role (not the superuser) ──────────────────────────
if ([string]::IsNullOrWhiteSpace($Password)) {
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_'
    $bytes    = [byte[]]::new(24)
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $Password = -join ($bytes | ForEach-Object { $alphabet[$_ % $alphabet.Length] })
    Write-Host "Generated a random password for the '$role' role." -ForegroundColor Cyan
}
if ($Password -match "['\\]") { throw "Password must not contain a single quote or backslash." }

# ── Back up pg_hba.conf ─────────────────────────────────────────────────────────
$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$hba.ifgf-backup-$stamp"
Copy-Item -Path $hba -Destination $backup -Force
Write-Host "Backed up pg_hba.conf to:" -ForegroundColor DarkGray
Write-Host "  $backup" -ForegroundColor DarkGray

$restored = $false

try {
    # ── Prepend trust rules. First match wins in pg_hba, so these take effect
    #    without removing anything that was already there. ────────────────────────
    $original = Get-Content $hba -Raw

    $trustBlock = @"
# === IFGF TEMPORARY TRUST - added $stamp ===
# If you are reading this, the setup script did not restore the file.
# Delete these three lines and restart the $ServiceName service.
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
# === END IFGF TEMPORARY TRUST ===

"@

    Set-Content -Path $hba -Value ($trustBlock + $original) -Encoding ascii

    Write-Host "Restarting $ServiceName (trust window opens)..." -ForegroundColor Yellow
    Restart-Service -Name $ServiceName -Force
    Start-Sleep -Seconds 3

    # ── Create the role (idempotent) ────────────────────────────────────────────
    $sql = @"
DO `$`$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$role') THEN
        CREATE ROLE $role WITH LOGIN PASSWORD '$Password';
    ELSE
        ALTER ROLE $role WITH LOGIN PASSWORD '$Password';
    END IF;
END
`$`$;
SELECT 'role ready: $role' AS status;
"@

    $sql | & $psql -U postgres -h 127.0.0.1 -d postgres -v ON_ERROR_STOP=1 -f -
    if ($LASTEXITCODE -ne 0) { throw "Failed to create or alter the role." }

    foreach ($db in @($dbLive, $dbTest)) {
        $exists = & $psql -U postgres -h 127.0.0.1 -d postgres -tAc `
            "SELECT 1 FROM pg_database WHERE datname = '$db'"
        if ($exists -ne '1') {
            & $psql -U postgres -h 127.0.0.1 -d postgres -v ON_ERROR_STOP=1 -c `
                "CREATE DATABASE $db OWNER $role ENCODING 'UTF8' TEMPLATE template0"
            if ($LASTEXITCODE -ne 0) { throw "Failed to create database $db." }
            Write-Host "Created database $db" -ForegroundColor Green
        } else {
            Write-Host "Database $db already exists - left alone" -ForegroundColor DarkGray
        }
    }
}
finally {
    # ── ALWAYS restore, even on failure or Ctrl-C ───────────────────────────────
    Write-Host "Restoring pg_hba.conf (trust window closes)..." -ForegroundColor Yellow
    try {
        Copy-Item -Path $backup -Destination $hba -Force
        Restart-Service -Name $ServiceName -Force
        Start-Sleep -Seconds 3

        $now = Get-Content $hba -Raw
        if ($now -match 'IFGF TEMPORARY TRUST') {
            Write-Host "!! pg_hba.conf STILL CONTAINS THE TRUST BLOCK." -ForegroundColor Red
            Write-Host "!! Copy $backup over $hba and restart $ServiceName." -ForegroundColor Red
        } else {
            $restored = $true
            Write-Host "pg_hba.conf restored - password authentication is back on." -ForegroundColor Green
        }
    } catch {
        Write-Host "!! COULD NOT RESTORE pg_hba.conf automatically: $_" -ForegroundColor Red
        Write-Host "!! Copy this file back over $hba by hand and restart the service:" -ForegroundColor Red
        Write-Host "!!   $backup" -ForegroundColor Red
    }
}

if (-not $restored) {
    throw "pg_hba.conf was not confirmed restored. Fix that before doing anything else."
}

# ── Persist the ifgf password ───────────────────────────────────────────────────
$content = Get-Content $envFile -Raw
if ($content -match '(?m)^IFGF_PG_PASSWORD=.*$') {
    $content = $content -replace '(?m)^IFGF_PG_PASSWORD=.*$', "IFGF_PG_PASSWORD=$Password"
} else {
    $content = $content.TrimEnd() + "`nIFGF_PG_PASSWORD=$Password`n"
}
Set-Content -Path $envFile -Value $content -Encoding utf8

$acl = Get-Acl $envFile
$acl.SetAccessRuleProtection($true, $false)
$acl.Access | ForEach-Object { $acl.RemoveAccessRule($_) | Out-Null }
$acl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule(
    "$env:USERDOMAIN\$env:USERNAME", 'FullControl', 'Allow')))
Set-Acl -Path $envFile -AclObject $acl

# ── Verify the new role can actually log in WITH a password ─────────────────────
$env:PGPASSWORD = $Password
$check = & $psql -U $role -h 127.0.0.1 -d $dbLive -tAc "SELECT current_user || '@' || current_database()"
$env:PGPASSWORD = $null

Write-Host ""
if ($LASTEXITCODE -eq 0) {
    Write-Host "Verified password login: $check" -ForegroundColor Green
} else {
    Write-Host "Role created, but the verification login failed. Check $envFile." -ForegroundColor Yellow
}

Write-Host "Password saved to $envFile (readable only by you)." -ForegroundColor Green
Write-Host "You never need to enter it again." -ForegroundColor Green
Write-Host ""
Write-Host "Next:" -ForegroundColor Cyan
Write-Host "  php artisan migrate --database=pgsql --path=database/migrations/ifgf" -ForegroundColor Cyan
Write-Host "  php artisan ifgf:demo:seed" -ForegroundColor Cyan
