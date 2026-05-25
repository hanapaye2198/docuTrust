$ErrorActionPreference = 'Stop'

param(
    [string] $AppBasePath = '/var/www/docutrust',
    [string] $EnvFile = '',
    [string] $RedisHost = '',
    [string] $RedisPort = '',
    [string] $RedisPassword = '',
    [string] $RedisDb = '',
    [string] $RedisPrefix = '',
    [string] $DefaultQueue = '',
    [string] $DocumentsQueue = '',
    [string] $NotificationsQueue = '',
    [string] $EinvoicesQueue = ''
)

if ([string]::IsNullOrWhiteSpace($EnvFile)) {
    $EnvFile = Join-Path $AppBasePath 'shared/.env'
}

if (-not (Test-Path $EnvFile)) {
    throw "Missing env file: $EnvFile"
}

$redisCli = Get-Command redis-cli -ErrorAction SilentlyContinue

if (-not $redisCli) {
    throw 'redis-cli is required'
}

function Read-EnvValue {
    param(
        [string] $Path,
        [string] $Key
    )

    $line = Select-String -Path $Path -Pattern "^$([regex]::Escape($Key))=" | Select-Object -Last 1
    if (-not $line) {
        return ''
    }

    $value = ($line.Line -split '=', 2)[1]
    return $value.Trim('"')
}

if ([string]::IsNullOrWhiteSpace($RedisHost)) { $RedisHost = Read-EnvValue -Path $EnvFile -Key 'REDIS_HOST' }
if ([string]::IsNullOrWhiteSpace($RedisPort)) { $RedisPort = Read-EnvValue -Path $EnvFile -Key 'REDIS_PORT' }
if ([string]::IsNullOrWhiteSpace($RedisPassword)) { $RedisPassword = Read-EnvValue -Path $EnvFile -Key 'REDIS_PASSWORD' }
if ([string]::IsNullOrWhiteSpace($RedisDb)) { $RedisDb = Read-EnvValue -Path $EnvFile -Key 'REDIS_DB' }
if ([string]::IsNullOrWhiteSpace($RedisPrefix)) { $RedisPrefix = Read-EnvValue -Path $EnvFile -Key 'REDIS_PREFIX' }

if ([string]::IsNullOrWhiteSpace($DocumentsQueue)) { $DocumentsQueue = Read-EnvValue -Path $EnvFile -Key 'DOCUTRUST_QUEUE_DOCUMENTS' }
if ([string]::IsNullOrWhiteSpace($NotificationsQueue)) { $NotificationsQueue = Read-EnvValue -Path $EnvFile -Key 'DOCUTRUST_QUEUE_NOTIFICATIONS' }
if ([string]::IsNullOrWhiteSpace($EinvoicesQueue)) { $EinvoicesQueue = Read-EnvValue -Path $EnvFile -Key 'DOCUTRUST_QUEUE_EINVOICES' }
if ([string]::IsNullOrWhiteSpace($DefaultQueue)) { $DefaultQueue = Read-EnvValue -Path $EnvFile -Key 'REDIS_QUEUE' }

if ([string]::IsNullOrWhiteSpace($RedisHost)) { $RedisHost = '127.0.0.1' }
if ([string]::IsNullOrWhiteSpace($RedisPort)) { $RedisPort = '6379' }
if ([string]::IsNullOrWhiteSpace($RedisDb)) { $RedisDb = '0' }
if ([string]::IsNullOrWhiteSpace($DefaultQueue)) { $DefaultQueue = 'default' }
if ([string]::IsNullOrWhiteSpace($DocumentsQueue)) { $DocumentsQueue = 'documents' }
if ([string]::IsNullOrWhiteSpace($NotificationsQueue)) { $NotificationsQueue = 'notifications' }
if ([string]::IsNullOrWhiteSpace($EinvoicesQueue)) { $EinvoicesQueue = 'einvoices' }

function Get-QueueDepth {
    param(
        [string] $QueueName
    )

    $key = "${RedisPrefix}queues:$QueueName"
    $args = @('-h', $RedisHost, '-p', $RedisPort, '-n', $RedisDb)

    if (-not [string]::IsNullOrWhiteSpace($RedisPassword) -and $RedisPassword -ne 'null') {
        $args += @('-a', $RedisPassword)
    }

    $args += @('LLEN', $key)
    $result = & $redisCli.Source @args

    if ($LASTEXITCODE -ne 0) {
        throw "Unable to read queue depth for $QueueName"
    }

    return ($result | Out-String).Trim()
}

$rows = @(
    [pscustomobject]@{ queue = 'default'; depth = Get-QueueDepth -QueueName $DefaultQueue },
    [pscustomobject]@{ queue = 'documents'; depth = Get-QueueDepth -QueueName $DocumentsQueue },
    [pscustomobject]@{ queue = 'notifications'; depth = Get-QueueDepth -QueueName $NotificationsQueue },
    [pscustomobject]@{ queue = 'einvoices'; depth = Get-QueueDepth -QueueName $EinvoicesQueue }
)

$rows | Format-Table -AutoSize
