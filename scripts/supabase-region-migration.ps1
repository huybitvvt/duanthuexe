param(
    [ValidateSet('Preflight', 'Inventory', 'Migrate', 'Verify')]
    [string]$Action = 'Preflight'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$rollbackEnvPath = Join-Path $projectRoot '.env.supabase.tokyo.rollback'
$sourceEnvPath = if (Test-Path -LiteralPath $rollbackEnvPath) {
    $rollbackEnvPath
}
else {
    Join-Path $projectRoot '.env.supabase'
}
$destinationEnvPath = Join-Path $projectRoot '.env.local'
$backupDirectory = Join-Path $projectRoot 'backups\supabase-singapore'
$postgresImage = 'postgres:17-alpine'

function Get-EnvValue {
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

    $value = ($line -split '=', 2)[1].Trim()
    if (
        ($value.StartsWith('"') -and $value.EndsWith('"')) -or
        ($value.StartsWith("'") -and $value.EndsWith("'"))
    ) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    return $value
}

function Get-ConnectionParts {
    param([string]$ConnectionString)

    $uri = [Uri]$ConnectionString
    $separator = $uri.UserInfo.IndexOf(':')
    if ($separator -lt 1) {
        throw 'Database URL must contain a username and password.'
    }

    return [PSCustomObject]@{
        Host = $uri.Host
        Port = if ($uri.Port -gt 0) { $uri.Port.ToString() } else { '5432' }
        Database = $uri.AbsolutePath.TrimStart('/')
        Username = [Uri]::UnescapeDataString($uri.UserInfo.Substring(0, $separator))
        Password = [Uri]::UnescapeDataString($uri.UserInfo.Substring($separator + 1))
    }
}

function Invoke-PostgresTool {
    param(
        [string]$ConnectionString,
        [string]$Tool,
        [string[]]$ToolArguments,
        [string]$MountDirectory = ''
    )

    $connection = Get-ConnectionParts $ConnectionString
    $env:PGHOST = $connection.Host
    $env:PGPORT = $connection.Port
    $env:PGDATABASE = $connection.Database
    $env:PGUSER = $connection.Username
    $env:PGPASSWORD = $connection.Password
    $env:PGSSLMODE = 'require'
    $env:PGCONNECT_TIMEOUT = '20'

    try {
        $dockerArguments = @(
            'run', '--rm',
            '--env', 'PGHOST',
            '--env', 'PGPORT',
            '--env', 'PGDATABASE',
            '--env', 'PGUSER',
            '--env', 'PGPASSWORD',
            '--env', 'PGSSLMODE',
            '--env', 'PGCONNECT_TIMEOUT'
        )

        if ($MountDirectory) {
            $dockerArguments += @(
                '--mount', "type=bind,source=$MountDirectory,target=/backup"
            )
        }

        $dockerArguments += @($postgresImage, $Tool)
        $dockerArguments += $ToolArguments

        $output = & docker @dockerArguments
        if ($LASTEXITCODE -ne 0) {
            throw "$Tool failed with exit code $LASTEXITCODE"
        }

        return $output
    }
    finally {
        @(
            'PGHOST',
            'PGPORT',
            'PGDATABASE',
            'PGUSER',
            'PGPASSWORD',
            'PGSSLMODE',
            'PGCONNECT_TIMEOUT'
        ) | ForEach-Object {
            Remove-Item "Env:$_" -ErrorAction SilentlyContinue
        }
    }
}

function Invoke-Query {
    param(
        [string]$ConnectionString,
        [string]$Sql
    )

    return Invoke-PostgresTool `
        -ConnectionString $ConnectionString `
        -Tool 'psql' `
        -ToolArguments @(
            '--no-psqlrc',
            '--set', 'ON_ERROR_STOP=1',
            '--tuples-only',
            '--no-align',
            '--field-separator', '|',
            '--command', $Sql
        )
}

function Get-DatabaseSummary {
    param([string]$ConnectionString)

    $sql = @"
select
    current_database(),
    current_user,
    current_setting('server_version'),
    (select count(*) from information_schema.tables where table_schema = 'himoto'),
    case when exists (select 1 from information_schema.schemata where schema_name = 'himoto') then 1 else 0 end,
    case when to_regclass('himoto.migrations') is null then 0 else 1 end;
"@

    $row = (Invoke-Query -ConnectionString $ConnectionString -Sql $sql | Select-Object -Last 1)
    $parts = $row -split '\|'
    if ($parts.Count -ne 6) {
        throw "Unexpected preflight result: $row"
    }

    return [PSCustomObject]@{
        Database = $parts[0]
        User = $parts[1]
        Version = $parts[2]
        TableCount = [int]$parts[3]
        SchemaExists = $parts[4] -eq '1'
        MigrationTableExists = $parts[5] -eq '1'
    }
}

function Get-TableCounts {
    param([string]$ConnectionString)

    $tableSql = @"
select tablename
from pg_tables
where schemaname = 'himoto'
order by tablename;
"@
    $tables = @(
        Invoke-Query -ConnectionString $ConnectionString -Sql $tableSql |
            Where-Object { $_ -and $_.Trim() }
    )

    if ($tables.Count -eq 0) {
        return @()
    }

    $countStatements = foreach ($table in $tables) {
        $identifier = $table.Replace('"', '""')
        $literal = $table.Replace("'", "''")
        "select '$literal' as table_name, count(*)::bigint as row_count from himoto.`"$identifier`""
    }

    $countSql = ($countStatements -join "`nunion all`n") + "`norder by table_name;"
    return @(
        Invoke-Query -ConnectionString $ConnectionString -Sql $countSql |
            Where-Object { $_ -and $_.Trim() }
    )
}

function Get-ObjectCounts {
    param([string]$ConnectionString)

    $sql = @"
select object_type, object_count
from (
    select 'tables' as object_type, count(*)::bigint as object_count
    from pg_class c join pg_namespace n on n.oid = c.relnamespace
    where n.nspname = 'himoto' and c.relkind in ('r', 'p')
    union all
    select 'sequences', count(*)::bigint
    from pg_class c join pg_namespace n on n.oid = c.relnamespace
    where n.nspname = 'himoto' and c.relkind = 'S'
    union all
    select 'views', count(*)::bigint
    from pg_class c join pg_namespace n on n.oid = c.relnamespace
    where n.nspname = 'himoto' and c.relkind in ('v', 'm')
    union all
    select 'routines', count(*)::bigint
    from pg_proc p join pg_namespace n on n.oid = p.pronamespace
    where n.nspname = 'himoto'
) counts
order by object_type;
"@

    return @(
        Invoke-Query -ConnectionString $ConnectionString -Sql $sql |
            Where-Object { $_ -and $_.Trim() }
    )
}

function Assert-Equivalent {
    param(
        [string]$SourceConnection,
        [string]$DestinationConnection
    )

    Write-Host 'Comparing schema object counts...'
    $sourceObjects = @(Get-ObjectCounts $SourceConnection)
    $destinationObjects = @(Get-ObjectCounts $DestinationConnection)
    $objectDifference = @(Compare-Object $sourceObjects $destinationObjects)
    if ($objectDifference.Count -gt 0) {
        $objectDifference | Format-Table | Out-Host
        throw 'Schema object counts do not match.'
    }

    Write-Host 'Comparing exact row counts for every table...'
    $sourceRows = @(Get-TableCounts $SourceConnection)
    $destinationRows = @(Get-TableCounts $DestinationConnection)
    $rowDifference = @(Compare-Object $sourceRows $destinationRows)
    if ($rowDifference.Count -gt 0) {
        $rowDifference | Format-Table | Out-Host
        throw 'Table row counts do not match.'
    }

    Write-Host "Verified $($sourceRows.Count) tables with matching row counts."
}

$sourceConnection = Get-EnvValue $sourceEnvPath 'SUPABASE_DATABASE_URL'
$destinationVariable = if (
    Get-Content -LiteralPath $destinationEnvPath |
        Where-Object { $_ -match '^\s*DATABASE_URL_SINGAPORE\s*=' }
) {
    'DATABASE_URL_SINGAPORE'
}
else {
    'DATABASE_URL'
}
$destinationConnection = Get-EnvValue $destinationEnvPath $destinationVariable

$sourceParts = Get-ConnectionParts $sourceConnection
$destinationParts = Get-ConnectionParts $destinationConnection

if ($sourceParts.Host -notlike '*ap-northeast-1*') {
    throw "Refusing to migrate: source host is not Tokyo ($($sourceParts.Host))."
}
if ($destinationParts.Host -notlike '*ap-southeast-1*') {
    throw "Refusing to migrate: destination host is not Singapore ($($destinationParts.Host))."
}
if (
    $sourceParts.Host -eq $destinationParts.Host -and
    $sourceParts.Username -eq $destinationParts.Username
) {
    throw 'Refusing to migrate: source and destination are identical.'
}

Write-Host "Source:      $($sourceParts.Host):$($sourceParts.Port)"
Write-Host "Destination: $($destinationParts.Host):$($destinationParts.Port)"

$sourceSummary = Get-DatabaseSummary $sourceConnection
$destinationSummary = Get-DatabaseSummary $destinationConnection

Write-Host "Source PostgreSQL $($sourceSummary.Version), himoto tables: $($sourceSummary.TableCount)"
Write-Host "Destination PostgreSQL $($destinationSummary.Version), himoto tables: $($destinationSummary.TableCount)"

if ($Action -eq 'Preflight') {
    exit 0
}

if ($Action -eq 'Inventory') {
    $schemaSql = @"
select table_schema, count(*)
from information_schema.tables
where table_type = 'BASE TABLE'
  and table_schema not in ('information_schema', 'pg_catalog')
group by table_schema
order by table_schema;
"@
    Write-Host 'Source schemas:'
    Invoke-Query -ConnectionString $sourceConnection -Sql $schemaSql | Out-Host
    Write-Host 'Destination schemas:'
    Invoke-Query -ConnectionString $destinationConnection -Sql $schemaSql | Out-Host
    $publicTableSql = @"
select table_name
from information_schema.tables
where table_schema = 'public' and table_type = 'BASE TABLE'
order by table_name;
"@
    Write-Host 'Source public tables:'
    Invoke-Query -ConnectionString $sourceConnection -Sql $publicTableSql | Out-Host
    Write-Host 'Destination public tables:'
    Invoke-Query -ConnectionString $destinationConnection -Sql $publicTableSql | Out-Host
    $managedDataSql = @"
select 'auth.users', count(*) from auth.users
union all select 'auth.identities', count(*) from auth.identities
union all select 'storage.buckets', count(*) from storage.buckets
union all select 'storage.objects', count(*) from storage.objects
union all select 'public.canary_verification_runs', count(*) from public.canary_verification_runs
order by 1;
"@
    Write-Host 'Source managed/test data counts:'
    Invoke-Query -ConnectionString $sourceConnection -Sql $managedDataSql | Out-Host
    exit 0
}

if ($Action -eq 'Verify') {
    Assert-Equivalent $sourceConnection $destinationConnection
    exit 0
}

if ($destinationSummary.SchemaExists -or $destinationSummary.TableCount -gt 0) {
    throw 'Destination schema himoto is not empty. Refusing to overwrite it.'
}

New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dumpFileName = "himoto-tokyo-$timestamp.dump"
$dumpPath = Join-Path $backupDirectory $dumpFileName

Write-Host "Creating encrypted-transport backup: $dumpPath"
Invoke-PostgresTool `
    -ConnectionString $sourceConnection `
    -Tool 'pg_dump' `
    -MountDirectory $backupDirectory `
    -ToolArguments @(
        '--format=custom',
        '--schema=himoto',
        '--no-owner',
        '--no-acl',
        '--verbose',
        "--file=/backup/$dumpFileName"
    ) | Out-Host

if (-not (Test-Path -LiteralPath $dumpPath)) {
    throw 'Backup file was not created.'
}
if ((Get-Item -LiteralPath $dumpPath).Length -le 0) {
    throw 'Backup file is empty.'
}

Write-Host 'Restoring into Singapore in one transaction...'
Invoke-PostgresTool `
    -ConnectionString $destinationConnection `
    -Tool 'pg_restore' `
    -MountDirectory $backupDirectory `
    -ToolArguments @(
        '--dbname=postgres',
        '--no-owner',
        '--no-acl',
        '--exit-on-error',
        '--single-transaction',
        '--verbose',
        "/backup/$dumpFileName"
    ) | Out-Host

Assert-Equivalent $sourceConnection $destinationConnection
Write-Host "Migration completed. Backup retained at: $dumpPath"
