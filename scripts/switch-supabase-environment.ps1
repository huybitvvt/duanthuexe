$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localEnvPath = Join-Path $projectRoot '.env.local'
$supabaseEnvPath = Join-Path $projectRoot '.env.supabase'
$rollbackEnvPath = Join-Path $projectRoot '.env.supabase.tokyo.rollback'

function Get-EnvLine {
    param(
        [string]$Path,
        [string]$Name
    )

    $escapedName = [regex]::Escape($Name)
    $line = Get-Content -LiteralPath $Path |
        Where-Object { $_ -match "^\s*$escapedName\s*=" } |
        Select-Object -Last 1

    if (-not $line) {
        throw "Missing $Name in $Path"
    }

    return $line
}

function Get-LineValue {
    param([string]$Line)

    $value = ($Line -split '=', 2)[1].Trim()
    if (
        ($value.StartsWith('"') -and $value.EndsWith('"')) -or
        ($value.StartsWith("'") -and $value.EndsWith("'"))
    ) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    return $value
}

function Format-EnvValue {
    param([string]$Value)

    $escaped = $Value.Replace('\', '\\').Replace("'", "\'")
    return "'$escaped'"
}

function Set-EnvValue {
    param(
        [string[]]$Lines,
        [string]$Name,
        [string]$Value
    )

    $escapedName = [regex]::Escape($Name)
    $found = $false
    $updated = foreach ($line in $Lines) {
        if ($line -match "^\s*$escapedName\s*=") {
            $found = $true
            "$Name=$(Format-EnvValue $Value)"
        }
        else {
            $line
        }
    }

    if (-not $found) {
        $updated += "$Name=$(Format-EnvValue $Value)"
    }

    return @($updated)
}

$singaporeLine = Get-EnvLine $localEnvPath 'SUPABASE_DATABASE_URL'
$singaporeUrl = Get-LineValue $singaporeLine
$singaporeUri = [Uri]$singaporeUrl
if ($singaporeUri.Host -notlike '*ap-southeast-1*') {
    throw "SUPABASE_DATABASE_URL in .env.local is not Singapore ($($singaporeUri.Host))."
}

$currentLine = Get-EnvLine $supabaseEnvPath 'SUPABASE_DATABASE_URL'
$currentUrl = Get-LineValue $currentLine
$currentUri = [Uri]$currentUrl

if ($currentUri.Host -like '*ap-northeast-1*') {
    if (-not (Test-Path -LiteralPath $rollbackEnvPath)) {
        Copy-Item -LiteralPath $supabaseEnvPath -Destination $rollbackEnvPath
    }
    else {
        $rollbackLine = Get-EnvLine $rollbackEnvPath 'SUPABASE_DATABASE_URL'
        $rollbackUri = [Uri](Get-LineValue $rollbackLine)
        if ($rollbackUri.Host -notlike '*ap-northeast-1*') {
            throw 'Existing rollback file is not a Tokyo connection. Refusing to overwrite it.'
        }
    }
}
elseif ($currentUri.Host -notlike '*ap-southeast-1*') {
    throw "Unexpected current Supabase host: $($currentUri.Host)"
}

$updated = Get-Content -LiteralPath $supabaseEnvPath | ForEach-Object {
    if ($_ -match '^\s*SUPABASE_DATABASE_URL\s*=') {
        $singaporeLine
    }
    else {
        $_
    }
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($supabaseEnvPath, $updated, $utf8WithoutBom)

$userInfoSeparator = $singaporeUri.UserInfo.IndexOf(':')
if ($userInfoSeparator -lt 1) {
    throw 'Singapore database URL must contain a username and password.'
}
$databaseUsername = [Uri]::UnescapeDataString(
    $singaporeUri.UserInfo.Substring(0, $userInfoSeparator)
)
$databasePassword = [Uri]::UnescapeDataString(
    $singaporeUri.UserInfo.Substring($userInfoSeparator + 1)
)
$databasePort = if ($singaporeUri.Port -gt 0) {
    $singaporeUri.Port.ToString()
}
else {
    '5432'
}

$localLines = @(Get-Content -LiteralPath $localEnvPath)
$localLines = @(Set-EnvValue $localLines 'DB_HOST' $singaporeUri.Host)
$localLines = @(Set-EnvValue $localLines 'DB_PORT' $databasePort)
$localLines = @(Set-EnvValue $localLines 'DB_DATABASE' $singaporeUri.AbsolutePath.TrimStart('/'))
$localLines = @(Set-EnvValue $localLines 'DB_USERNAME' $databaseUsername)
$localLines = @(Set-EnvValue $localLines 'DB_PASSWORD' $databasePassword)
$localLines = @(Set-EnvValue $localLines 'DB_SSLMODE' 'require')
[System.IO.File]::WriteAllLines($localEnvPath, $localLines, $utf8WithoutBom)

Write-Host "Active Supabase environment: $($singaporeUri.Host):$($singaporeUri.Port)"
Write-Host "Tokyo rollback environment retained at: $rollbackEnvPath"
