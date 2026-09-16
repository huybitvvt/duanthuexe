param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8091,
    [string]$HostAddress = "127.0.0.1",
    [string]$PhpBinary = ""
)

$ErrorActionPreference = "Stop"
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot "..")).Path
. (Join-Path $PSScriptRoot "import-local-env.ps1") -ProjectRoot $projectRoot

if (-not $PhpBinary) {
    if ($env:HIMOTO_PHP_BIN) {
        $PhpBinary = $env:HIMOTO_PHP_BIN
    } else {
        $portablePhp = Join-Path $projectRoot "..\tools\php74\php.exe"
        if (Test-Path -LiteralPath $portablePhp) {
            $PhpBinary = (Resolve-Path -LiteralPath $portablePhp).Path
        } else {
            $phpCommand = Get-Command php -ErrorAction SilentlyContinue
            if ($phpCommand) {
                $PhpBinary = $phpCommand.Source
            }
        }
    }
}

if (-not $PhpBinary -or -not (Test-Path -LiteralPath $PhpBinary)) {
    throw "PHP was not found. Set HIMOTO_PHP_BIN to an absolute php.exe path."
}

Push-Location $projectRoot
try {
    & $PhpBinary artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw "Laravel config cache could not be cleared."
    }

    Write-Host "Starting HIMOTO at http://${HostAddress}:$Port (secrets loaded into this process only)."
    & $PhpBinary artisan serve --host=$HostAddress --port=$Port
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
