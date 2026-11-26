param([ValidateRange(1024,65535)][int]$Port=8000, [string]$Php='php')
$ErrorActionPreference='Stop'
$server=$null
try {
    $phpPath=(Get-Command $Php -CommandType Application -ErrorAction Stop).Source
    $listener=New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback,$Port)
    try { $listener.Start() } finally { $listener.Stop() }
    $info=New-Object Diagnostics.ProcessStartInfo
    $info.FileName=$phpPath
    $info.WorkingDirectory=$PSScriptRoot
    $info.Arguments='-d display_errors=0 -d log_errors=0 -S 127.0.0.1:'+$Port+' -t . router.php'
    $info.UseShellExecute=$false
    $info.CreateNoWindow=$true
    $info.RedirectStandardOutput=$true
    $info.RedirectStandardError=$true
    $server=New-Object Diagnostics.Process
    $server.StartInfo=$info
    if(!$server.Start()) { throw 'PHP could not start.' }
    $stdout=$server.StandardOutput.ReadToEndAsync()
    $stderr=$server.StandardError.ReadToEndAsync()
    $ready=$false
    for($attempt=0;$attempt -lt 30;$attempt++) {
        if($server.HasExited) { throw 'PHP could not start. The port may already be in use.' }
        try {
            $response=Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port/index.html" -TimeoutSec 1
            if($response.StatusCode -eq 200) { $ready=$true; break }
        } catch { Start-Sleep -Milliseconds 100 }
    }
    if(!$ready) { throw 'PHP did not become ready. Check your local PHP configuration.' }
    Write-Host "Website: http://127.0.0.1:$Port/index.html"
    Write-Host "Admin:   http://127.0.0.1:$Port/admin/login.php"
    Write-Host 'Press Ctrl+C in this terminal to stop the local server.'
    while(!$server.HasExited) { Start-Sleep -Milliseconds 250 }
} catch { Write-Host $_.Exception.Message; exit 1 }
finally {
    if($null -ne $server) {
        if(!$server.HasExited) { $server.Kill(); $server.WaitForExit() }
        $server.Dispose()
    }
}
