$ErrorActionPreference = 'Stop'

$systemctl = Get-Command systemctl -ErrorAction SilentlyContinue

if (-not $systemctl) {
    Write-Error 'systemctl is required'
}

$queueUnits = @(& $systemctl.Source list-units 'docutrust-queue@*' --all --no-legend --no-pager 2>$null |
    ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '') {
            return
        }

        ($line -split '\s+')[0]
    } |
    Sort-Object)

$units = @($queueUnits + @(
    'docutrust-blockchain',
    'docutrust-reverb'
))

foreach ($unit in $units) {
    if ([string]::IsNullOrWhiteSpace($unit)) {
        continue
    }

    & $systemctl.Source --no-pager --full status $unit
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Unable to read status for $unit"
    }

    Write-Host ''
}
