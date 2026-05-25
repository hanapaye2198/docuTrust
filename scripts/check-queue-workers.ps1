$ErrorActionPreference = 'Stop'

$units = @(
    'docutrust-queue@default',
    'docutrust-queue@documents',
    'docutrust-queue@notifications',
    'docutrust-queue@einvoices',
    'docutrust-blockchain'
)

$systemctl = Get-Command systemctl -ErrorAction SilentlyContinue

if (-not $systemctl) {
    Write-Error 'systemctl is required'
}

foreach ($unit in $units) {
    & $systemctl.Source --no-pager --full status $unit
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Unable to read status for $unit"
    }

    Write-Host ''
}
