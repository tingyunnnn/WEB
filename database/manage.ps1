param(
    [Parameter(Mandatory=$true)][ValidateSet('Install','Check','CreateAdmin','Test')][string]$Action,
    [string]$DbHost='127.0.0.1', [int]$Port=3306,
    [string]$Database='tidebites', [string]$AppUser='tidebites_app',
    [string]$ConfigPath='', [string]$Php='php'
)
$ErrorActionPreference='Stop'

function Read-LocalSecret([string]$Prompt) {
    $secure=Read-Host $Prompt -AsSecureString
    $pointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer); $secure.Dispose() }
}

$payload=@{host=$DbHost;port=$Port;database=$Database;app_user=$AppUser}
$process=$null
$bytes=$null
$exitCode=1
try {
    if($Action -in 'Install','Test') {
        $payload.root_password=Read-LocalSecret 'Existing MySQL root password (hidden, local input only)'
    }
    if($Action -eq 'Install') {
        $payload.app_password=Read-LocalSecret 'New TideBites app password (at least 16 bytes)'
        $confirm=Read-LocalSecret 'Confirm app password'
        if($payload.app_password -cne $confirm) { throw 'Passwords do not match. Nothing was changed.' }
        $confirm=$null
    }
    if($Action -eq 'CreateAdmin') {
        $payload.admin_username=Read-Host 'New website admin username'
        $payload.admin_password=Read-LocalSecret 'New website admin password (12-72 bytes)'
        $confirm=Read-LocalSecret 'Confirm admin password'
        if($payload.admin_password -cne $confirm) { throw 'Passwords do not match. Nothing was changed.' }
        $confirm=$null
    }
    $utf8=New-Object Text.UTF8Encoding($false)
    $bytes=$utf8.GetBytes(($payload | ConvertTo-Json -Compress))
    $info=New-Object Diagnostics.ProcessStartInfo
    $info.FileName=(Get-Command $Php -CommandType Application -ErrorAction Stop).Source
    $info.Arguments='"'+(Join-Path $PSScriptRoot 'manage.php')+'" '+$Action.ToLowerInvariant()
    $info.WorkingDirectory=Split-Path -Parent $PSScriptRoot
    $info.UseShellExecute=$false
    $info.CreateNoWindow=$true
    $info.RedirectStandardInput=$true
    $info.RedirectStandardOutput=$true
    $info.RedirectStandardError=$true
    $info.StandardOutputEncoding=$utf8
    $info.StandardErrorEncoding=$utf8
    if(-not [string]::IsNullOrWhiteSpace($ConfigPath)) { $info.EnvironmentVariables['TIDEBITES_CONFIG']=$ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($ConfigPath) }
    $process=New-Object Diagnostics.Process
    $process.StartInfo=$info
    if(!$process.Start()) { throw 'Could not start PHP.' }
    $out=$process.StandardOutput.ReadToEndAsync()
    $err=$process.StandardError.ReadToEndAsync()
    $process.StandardInput.BaseStream.Write($bytes,0,$bytes.Length)
    $process.StandardInput.Close()
    [Array]::Clear($bytes,0,$bytes.Length)
    $bytes=$null
    $payload.Clear()
    $process.WaitForExit()
    Write-Output ($out.GetAwaiter().GetResult())
    if($err.GetAwaiter().GetResult()) { Write-Warning 'PHP produced diagnostics. Raw stderr is withheld to protect credentials.' }
    $exitCode=$process.ExitCode
} catch { Write-Error $_.Exception.Message }
finally {
    if($null -ne $bytes) { [Array]::Clear($bytes,0,$bytes.Length) }
    $payload.Clear()
    $confirm=$null
    if($null -ne $process) { $process.Dispose() }
}
exit $exitCode
