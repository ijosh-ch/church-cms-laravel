<#
.SYNOPSIS
    One-time PostgreSQL setup for IFGF on this machine.

.DESCRIPTION
    Creates the 'ifgf' login role and the two databases (live + test), then writes the
    password into the machine-wide credential file at ~/.ifgf/postgres.env so it never
    has to be typed again.

    Run this ONCE. Afterwards every IFGF project on this machine reads the credentials
    from that file automatically (see bootstrap/global-env.php).

.PARAMETER Password
    The password to give the 'ifgf' role. Omit it and the script generates a strong
    random one for you — you never have to invent or remember it, because it is written
    straight to the credential file.

.PARAMETER PostgresSuperuser
    The existing superuser used to create the role. Defaults to 'postgres'. You will be
    prompted for ITS password by psql — that prompt is PostgreSQL's own, not this script's.

.EXAMPLE
    .\tools\setup-postgres.ps1
    # generates a random password, creates everything, saves it

.EXAMPLE
    .\tools\setup-postgres.ps1 -Password 'my-own-choice'
#>

[CmdletBinding()]
param(
    [string]$Password,
    [string]$PostgresSuperuser = 'postgres',
    [string]$PgBin = 'C:\Program Files\PostgreSQL\17\bin'
)

$ErrorActionPreference = 'Stop'

$psql = Join-Path $PgBin 'psql.exe'
if (-not (Test-Path $psql)) {
    throw "psql not found at $psql. Pass -PgBin with your PostgreSQL bin directory."
}

$envDir  = Join-Path $env:USERPROFILE '.ifgf'
$envFile = Join-Path $envDir 'postgres.env'

if (-not (Test-Path $envFile)) {
    throw "Credential file not found at $envFile. It should have been created already."
}

# ── Password ────────────────────────────────────────────────────────────────────
if ([string]::IsNullOrWhiteSpace($Password)) {
    # 24 chars from an unambiguous alphabet. No quotes, backslashes or '#', so it is
    # safe inside both an env file and a SQL string literal without escaping.
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_'
    $bytes    = [byte[]]::new(24)
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $Password = -join ($bytes | ForEach-Object { $alphabet[$_ % $alphabet.Length] })
    Write-Host "Generated a random password for the 'ifgf' role." -ForegroundColor Cyan
}

if ($Password -match "['\\]") {
    throw "Password must not contain a single quote or backslash."
}

# ── Read the target names back from the credential file ─────────────────────────
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

Write-Host "Role: $role   Databases: $dbLive, $dbTest" -ForegroundColor Cyan
Write-Host "psql will now ask for the '$PostgresSuperuser' password." -ForegroundColor Yellow

# ── Create role and databases (idempotent) ──────────────────────────────────────
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
SELECT 'role ready' AS status;
"@

$sql | & $psql -U $PostgresSuperuser -h 127.0.0.1 -d postgres -v ON_ERROR_STOP=1 -f -
if ($LASTEXITCODE -ne 0) { throw "Failed to create or alter the role." }

# CREATE DATABASE cannot run inside a DO block, so guard each one separately.
foreach ($db in @($dbLive, $dbTest)) {
    $exists = & $psql -U $PostgresSuperuser -h 127.0.0.1 -d postgres -tAc `
        "SELECT 1 FROM pg_database WHERE datname = '$db'"
    if ($exists -ne '1') {
        & $psql -U $PostgresSuperuser -h 127.0.0.1 -d postgres -v ON_ERROR_STOP=1 -c `
            "CREATE DATABASE $db OWNER $role ENCODING 'UTF8' TEMPLATE template0"
        if ($LASTEXITCODE -ne 0) { throw "Failed to create database $db." }
        Write-Host "Created database $db" -ForegroundColor Green
    } else {
        Write-Host "Database $db already exists - left alone" -ForegroundColor DarkGray
    }
}

# ── Persist the password ────────────────────────────────────────────────────────
$content = Get-Content $envFile -Raw
if ($content -match '(?m)^IFGF_PG_PASSWORD=.*$') {
    $content = $content -replace '(?m)^IFGF_PG_PASSWORD=.*$', "IFGF_PG_PASSWORD=$Password"
} else {
    $content = $content.TrimEnd() + "`nIFGF_PG_PASSWORD=$Password`n"
}
Set-Content -Path $envFile -Value $content -Encoding utf8 -NoNewline:$false

# Restrict to the current user only — this file now holds a live credential.
$acl = Get-Acl $envFile
$acl.SetAccessRuleProtection($true, $false)
$acl.Access | ForEach-Object { $acl.RemoveAccessRule($_) | Out-Null }
$acl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule(
    "$env:USERDOMAIN\$env:USERNAME", 'FullControl', 'Allow')))
Set-Acl -Path $envFile -AclObject $acl

Write-Host ""
Write-Host "Done. Password saved to $envFile (readable only by you)." -ForegroundColor Green
Write-Host "You never need to enter it again." -ForegroundColor Green
Write-Host ""
Write-Host "Next:  php artisan migrate --database=pgsql" -ForegroundColor Cyan
