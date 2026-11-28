# Exercise readiness and Ctrl+C-equivalent pipeline cancellation without desktop input.
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$probe=New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback,0)
$probe.Start()
$port=$probe.LocalEndpoint.Port
$probe.Stop()
$pipeline=[PowerShell]::Create()
$ready=$false
$stopped=$false
try {
    $null=$pipeline.AddCommand((Join-Path $projectRoot 'start.ps1')).AddParameter('Port',$port)
    $pending=$pipeline.BeginInvoke()
    for($attempt=0;$attempt -lt 60;$attempt++) {
        if($pending.IsCompleted) { throw 'Launcher ended before readiness.' }
        try {
            $response=Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$port/index.html" -TimeoutSec 1
            if($response.StatusCode -eq 200) { $ready=$true; break }
        } catch { Start-Sleep -Milliseconds 100 }
    }
    if(!$ready) { throw 'Launcher readiness failed.' }
    $blocked=$false
    try { Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$port/database/tidebites.sql" -TimeoutSec 2 > $null }
    catch { $blocked=[int]$_.Exception.Response.StatusCode -eq 404 }
    if(!$blocked) { throw 'Launcher did not use the protected router.' }
} finally {
    $pipeline.Stop()
    $pipeline.Dispose()
    for($attempt=0;$attempt -lt 40;$attempt++) {
        $socket=New-Object Net.Sockets.TcpClient
        try { $socket.Connect('127.0.0.1',$port); Start-Sleep -Milliseconds 100 }
        catch { $stopped=$true; break }
        finally { $socket.Dispose() }
    }
}
if(!$stopped) { throw 'Launcher left its PHP process running.' }
@{result='PASS';readiness=$ready;router_protection=$blocked;server_stopped=$stopped} | ConvertTo-Json -Compress
