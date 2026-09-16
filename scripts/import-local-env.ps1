param(
    [string]$ProjectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot "..")).Path
)

function Import-HimotoDotEnv {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if (-not $trimmed -or $trimmed.StartsWith("#")) {
            continue
        }
        if ($trimmed -notmatch '^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$') {
            throw "Invalid dotenv line in $Path. Expected KEY=VALUE."
        }

        $name = $Matches[1]
        $value = $Matches[2].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        [Environment]::SetEnvironmentVariable($name, $value, "Process")
    }
}

Import-HimotoDotEnv -Path (Join-Path $ProjectRoot ".env.supabase")
Import-HimotoDotEnv -Path (Join-Path $ProjectRoot ".env.cloudinary")

if ($env:SUPABASE_DATABASE_URL) {
    $env:DATABASE_URL = $env:SUPABASE_DATABASE_URL
    $env:DB_CONNECTION = "pgsql"

    if ([string]::IsNullOrWhiteSpace($env:DB_SCHEMA)) {
        $env:DB_SCHEMA = "himoto"
    }
    if ([string]::IsNullOrWhiteSpace($env:DB_SSLMODE)) {
        $env:DB_SSLMODE = "require"
    }
}

if (-not $env:DATABASE_URL -and -not $env:DB_PASSWORD) {
    throw "Missing database credentials. Put SUPABASE_DATABASE_URL in the ignored .env.supabase file."
}
