param(
    [Parameter(Mandatory = $true)]
    [string] $Name,

    [Parameter(Mandatory = $true)]
    [string] $Url,

    [ValidateSet('GET', 'POST', 'PUT', 'PATCH', 'DELETE')]
    [string] $Method = 'GET',

    [int] $Concurrency = 10,

    [int] $DurationSeconds = 30,

    [string] $Body = '',

    [string] $BodyBase64 = '',

    [string] $ContentType = 'application/json',

    [string] $BearerToken = '',

    [switch] $Json
)

$ErrorActionPreference = 'Stop'

function Get-Percentile {
    param(
        [double[]] $Values,
        [double] $Percentile
    )

    if ($Values.Count -eq 0) {
        return 0
    }

    $sorted = $Values | Sort-Object
    $index = [Math]::Ceiling(($Percentile / 100) * $sorted.Count) - 1
    $index = [Math]::Max(0, [Math]::Min($index, $sorted.Count - 1))

    return [Math]::Round($sorted[$index], 2)
}

function Merge-StatusCounts {
    param(
        [hashtable] $Target,
        [hashtable] $Source
    )

    foreach ($key in $Source.Keys) {
        if (-not $Target.ContainsKey($key)) {
            $Target[$key] = 0
        }

        $Target[$key] += [int] $Source[$key]
    }
}

$headers = @{}
if ($BearerToken.Trim() -ne '') {
    $headers['Authorization'] = 'Bearer ' + $BearerToken.Trim()
}

if ($BodyBase64.Trim() -ne '') {
    $Body = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($BodyBase64))
}

$jobs = @()
$startedAt = Get-Date

for ($worker = 0; $worker -lt $Concurrency; $worker++) {
    $jobs += Start-Job -ScriptBlock {
        param($ScenarioUrl, $ScenarioMethod, $ScenarioDurationSeconds, $ScenarioHeaders, $ScenarioBody, $ScenarioContentType)

        Add-Type -AssemblyName System.Net.Http

        $endAt = (Get-Date).AddSeconds($ScenarioDurationSeconds)
        $latencies = New-Object System.Collections.Generic.List[double]
        $statusCounts = @{}
        $success = 0
        $errors = 0
        $client = [System.Net.Http.HttpClient]::new()
        $client.Timeout = [TimeSpan]::FromSeconds(30)

        try {
            while ((Get-Date) -lt $endAt) {
                $request = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::$ScenarioMethod, $ScenarioUrl)
                $response = $null

                foreach ($headerName in $ScenarioHeaders.Keys) {
                    $null = $request.Headers.TryAddWithoutValidation($headerName, [string] $ScenarioHeaders[$headerName])
                }

                if ($ScenarioMethod -ne 'GET' -and $ScenarioBody -ne '') {
                    $request.Content = [System.Net.Http.StringContent]::new($ScenarioBody, [System.Text.Encoding]::UTF8, $ScenarioContentType)
                }

                $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()

                try {
                    $response = $client.SendAsync($request).GetAwaiter().GetResult()
                    $null = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
                    $stopwatch.Stop()

                    $latencies.Add($stopwatch.Elapsed.TotalMilliseconds)

                    $statusCode = [string] [int] $response.StatusCode
                    if (-not $statusCounts.ContainsKey($statusCode)) {
                        $statusCounts[$statusCode] = 0
                    }

                    $statusCounts[$statusCode] += 1
                    if ($response.IsSuccessStatusCode) {
                        $success += 1
                    } else {
                        $errors += 1
                    }
                } catch {
                    $stopwatch.Stop()
                    $latencies.Add($stopwatch.Elapsed.TotalMilliseconds)

                    $statusCode = 'ERR'
                    if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
                        $statusCode = [string] [int] $_.Exception.Response.StatusCode.value__
                    }

                    if (-not $statusCounts.ContainsKey($statusCode)) {
                        $statusCounts[$statusCode] = 0
                    }

                    $statusCounts[$statusCode] += 1
                    $errors += 1
                } finally {
                    $request.Dispose()
                    if ($response) {
                        $response.Dispose()
                    }
                }
            }
        } finally {
            $client.Dispose()
        }

        [pscustomobject]@{
            Success = $success
            Errors = $errors
            StatusCounts = $statusCounts
            Latencies = $latencies.ToArray()
        }
    } -ArgumentList $Url, $Method, $DurationSeconds, $headers, $Body, $ContentType
}

$results = Receive-Job -Job $jobs -Wait -AutoRemoveJob
$finishedAt = Get-Date

$allLatencies = New-Object System.Collections.Generic.List[double]
$aggregatedStatusCounts = @{}
$successCount = 0
$errorCount = 0

foreach ($result in $results) {
    $successCount += [int] $result.Success
    $errorCount += [int] $result.Errors
    Merge-StatusCounts -Target $aggregatedStatusCounts -Source $result.StatusCounts

    foreach ($latency in $result.Latencies) {
        $allLatencies.Add([double] $latency)
    }
}

$requestCount = $successCount + $errorCount
$elapsedSeconds = [Math]::Max(($finishedAt - $startedAt).TotalSeconds, 0.001)
$latencyValues = $allLatencies.ToArray()
$averageLatency = if ($latencyValues.Count -eq 0) { 0 } else { [Math]::Round(($latencyValues | Measure-Object -Average).Average, 2) }
$maxLatency = if ($latencyValues.Count -eq 0) { 0 } else { [Math]::Round(($latencyValues | Measure-Object -Maximum).Maximum, 2) }

$report = [pscustomobject]@{
    name = $Name
    url = $Url
    method = $Method
    concurrency = $Concurrency
    duration_seconds = $DurationSeconds
    started_at = $startedAt.ToUniversalTime().ToString('o')
    finished_at = $finishedAt.ToUniversalTime().ToString('o')
    requests = $requestCount
    successes = $successCount
    errors = $errorCount
    requests_per_second = [Math]::Round($requestCount / $elapsedSeconds, 2)
    latency_ms = [pscustomobject]@{
        average = $averageLatency
        p50 = Get-Percentile -Values $latencyValues -Percentile 50
        p95 = Get-Percentile -Values $latencyValues -Percentile 95
        p99 = Get-Percentile -Values $latencyValues -Percentile 99
        maximum = $maxLatency
    }
    status_counts = $aggregatedStatusCounts
}

if ($Json) {
    $report | ConvertTo-Json -Depth 6
    exit 0
}

Write-Host "Scenario: $($report.name)"
Write-Host "URL: $($report.method) $($report.url)"
Write-Host "Concurrency: $($report.concurrency) | Duration: $($report.duration_seconds)s"
Write-Host "Requests: $($report.requests) | Successes: $($report.successes) | Errors: $($report.errors)"
Write-Host "RPS: $($report.requests_per_second)"
Write-Host ("Latency (ms): avg={0} p50={1} p95={2} p99={3} max={4}" -f `
    $report.latency_ms.average,
    $report.latency_ms.p50,
    $report.latency_ms.p95,
    $report.latency_ms.p99,
    $report.latency_ms.maximum)
Write-Host "Status counts:"

foreach ($statusCode in ($report.status_counts.Keys | Sort-Object)) {
    Write-Host ("  {0}: {1}" -f $statusCode, $report.status_counts[$statusCode])
}
