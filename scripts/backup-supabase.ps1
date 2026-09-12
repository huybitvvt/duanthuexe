param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\backups"),
    [string]$DockerNetwork = ""
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($env:SUPABASE_DATABASE_URL)) {
    throw "Set SUPABASE_DATABASE_URL to the Supabase Session Pooler connection string first."
}

New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
$backupDirectory = (Resolve-Path -LiteralPath $OutputDirectory).Path
$backupFile = "himoto-{0}.dump" -f (Get-Date -Format "yyyyMMdd-HHmmss")

$dockerArguments = @(
    "run",
    "--rm",
    "--env", "SUPABASE_DATABASE_URL",
    "--env", "BACKUP_FILE=$backupFile",
    "--mount", "type=bind,source=$backupDirectory,target=/backups"
)

if (-not [string]::IsNullOrWhiteSpace($DockerNetwork)) {
    $dockerArguments += @("--network", $DockerNetwork)
}

$dockerArguments += @(
    "postgres:17-alpine",
    "sh", "-c",
    'pg_dump --format=custom --no-owner --no-acl --dbname="$SUPABASE_DATABASE_URL" --file="/backups/$BACKUP_FILE"'
)

& docker @dockerArguments

if ($LASTEXITCODE -ne 0) {
    throw "pg_dump failed with exit code $LASTEXITCODE."
}

$backupPath = Join-Path $backupDirectory $backupFile
Write-Output "Backup created: $backupPath"
