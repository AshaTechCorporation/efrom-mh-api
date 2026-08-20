Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not ('EdmsCredential.NativeMethods' -as [type])) {
    Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;

namespace EdmsCredential
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct NativeCredential
    {
        public UInt32 Flags;
        public UInt32 Type;
        public string TargetName;
        public string Comment;
        public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
        public UInt32 CredentialBlobSize;
        public IntPtr CredentialBlob;
        public UInt32 Persist;
        public UInt32 AttributeCount;
        public IntPtr Attributes;
        public string TargetAlias;
        public string UserName;
    }

    public static class NativeMethods
    {
        [DllImport("Advapi32.dll", EntryPoint = "CredReadW", CharSet = CharSet.Unicode, SetLastError = true)]
        public static extern bool CredRead(string target, UInt32 type, UInt32 flags, out IntPtr credentialPtr);

        [DllImport("Advapi32.dll", SetLastError = true)]
        public static extern void CredFree(IntPtr credentialPtr);
    }
}
'@
}

$script:EdmsCredentialTargets = [ordered]@{
    Vpn = 'EDMS-PROD-VPN'
    Ssh = 'EDMS-PROD-SSH'
    WebAdmin = 'EDMS-PROD-WEB-ADMIN'
    Database = 'EDMS-PROD-DB'
}

function Get-EdmsStoredCredential {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [ValidateSet('Vpn', 'Ssh', 'WebAdmin', 'Database')]
        [string]$Name
    )

    $target = $script:EdmsCredentialTargets[$Name]
    $credentialPointer = [IntPtr]::Zero
    $read = [EdmsCredential.NativeMethods]::CredRead(
        $target,
        1,
        0,
        [ref]$credentialPointer
    )

    if (-not $read) {
        $errorCode = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
        throw "Credential '$target' is unavailable in Windows Credential Manager (Win32 error $errorCode)."
    }

    try {
        $nativeCredential = [Runtime.InteropServices.Marshal]::PtrToStructure(
            $credentialPointer,
            [type][EdmsCredential.NativeCredential]
        )
        $password = if ($nativeCredential.CredentialBlobSize -gt 0) {
            [Runtime.InteropServices.Marshal]::PtrToStringUni(
                $nativeCredential.CredentialBlob,
                [int]($nativeCredential.CredentialBlobSize / 2)
            )
        } else {
            ''
        }
        $securePassword = ConvertTo-SecureString $password -AsPlainText -Force
        return [PSCredential]::new($nativeCredential.UserName, $securePassword)
    } finally {
        [EdmsCredential.NativeMethods]::CredFree($credentialPointer)
    }
}

function Test-EdmsStoredCredentials {
    [CmdletBinding()]
    param()

    foreach ($name in $script:EdmsCredentialTargets.Keys) {
        try {
            $credential = Get-EdmsStoredCredential -Name $name
            [pscustomobject]@{
                Name = $name
                Target = $script:EdmsCredentialTargets[$name]
                UserName = $credential.UserName
                Available = $true
            }
        } catch {
            [pscustomobject]@{
                Name = $name
                Target = $script:EdmsCredentialTargets[$name]
                UserName = $null
                Available = $false
            }
        }
    }
}

function New-EdmsSshAskPassDirectory {
    [CmdletBinding()]
    param()

    $directory = Join-Path ([IO.Path]::GetTempPath()) ("edms-ssh-" + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $directory -Force | Out-Null

    $askPassPowerShell = Join-Path $directory 'ssh-askpass.ps1'
    $askPassCommand = Join-Path $directory 'ssh-askpass.cmd'
    Set-Content -LiteralPath $askPassPowerShell -Encoding UTF8 -Value '[Console]::Out.Write($env:EDMS_SSH_PASSWORD)'
    Set-Content -LiteralPath $askPassCommand -Encoding ASCII -Value '@echo off', 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "%~dp0ssh-askpass.ps1"'

    return [pscustomobject]@{
        Directory = $directory
        AskPassCommand = $askPassCommand
    }
}

function Invoke-EdmsSshCommand {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [string]$Command,
        [string]$Server = '10.98.201.77',
        [int]$Port = 22
    )

    $credential = Get-EdmsStoredCredential -Name Ssh
    $askPass = New-EdmsSshAskPassDirectory
    $previousAskPass = $env:SSH_ASKPASS
    $previousAskPassRequire = $env:SSH_ASKPASS_REQUIRE
    $previousDisplay = $env:DISPLAY
    $previousPassword = $env:EDMS_SSH_PASSWORD

    try {
        $env:SSH_ASKPASS = $askPass.AskPassCommand
        $env:SSH_ASKPASS_REQUIRE = 'force'
        $env:DISPLAY = 'edms-deploy'
        $env:EDMS_SSH_PASSWORD = $credential.GetNetworkCredential().Password
        & ssh -p $Port -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1 "$($credential.UserName)@$Server" $Command
        if ($LASTEXITCODE -ne 0) {
            throw "SSH command failed with exit code $LASTEXITCODE."
        }
    } finally {
        $env:SSH_ASKPASS = $previousAskPass
        $env:SSH_ASKPASS_REQUIRE = $previousAskPassRequire
        $env:DISPLAY = $previousDisplay
        $env:EDMS_SSH_PASSWORD = $previousPassword
        Remove-Item -LiteralPath $askPass.Directory -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Connect-EdmsServer {
    [CmdletBinding()]
    param(
        [string]$Server = '10.98.201.77',
        [int]$Port = 22
    )

    $credential = Get-EdmsStoredCredential -Name Ssh
    $askPass = New-EdmsSshAskPassDirectory
    $previousAskPass = $env:SSH_ASKPASS
    $previousAskPassRequire = $env:SSH_ASKPASS_REQUIRE
    $previousDisplay = $env:DISPLAY
    $previousPassword = $env:EDMS_SSH_PASSWORD

    try {
        $env:SSH_ASKPASS = $askPass.AskPassCommand
        $env:SSH_ASKPASS_REQUIRE = 'force'
        $env:DISPLAY = 'edms-deploy'
        $env:EDMS_SSH_PASSWORD = $credential.GetNetworkCredential().Password
        & ssh -tt -p $Port -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1 "$($credential.UserName)@$Server"
    } finally {
        $env:SSH_ASKPASS = $previousAskPass
        $env:SSH_ASKPASS_REQUIRE = $previousAskPassRequire
        $env:DISPLAY = $previousDisplay
        $env:EDMS_SSH_PASSWORD = $previousPassword
        Remove-Item -LiteralPath $askPass.Directory -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Open-EdmsDatabaseTunnel {
    [CmdletBinding()]
    param(
        [string]$Server = '10.98.201.77',
        [int]$SshPort = 22,
        [int]$LocalPort = 3307
    )

    $existingListener = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $LocalPort -State Listen -ErrorAction SilentlyContinue
    if ($existingListener) {
        return [pscustomobject]@{
            LocalHost = '127.0.0.1'
            LocalPort = $LocalPort
            ProcessId = $existingListener.OwningProcess
            AlreadyRunning = $true
        }
    }

    $credential = Get-EdmsStoredCredential -Name Ssh
    $askPass = New-EdmsSshAskPassDirectory
    $previousAskPass = $env:SSH_ASKPASS
    $previousAskPassRequire = $env:SSH_ASKPASS_REQUIRE
    $previousDisplay = $env:DISPLAY
    $previousPassword = $env:EDMS_SSH_PASSWORD

    try {
        $env:SSH_ASKPASS = $askPass.AskPassCommand
        $env:SSH_ASKPASS_REQUIRE = 'force'
        $env:DISPLAY = 'edms-deploy'
        $env:EDMS_SSH_PASSWORD = $credential.GetNetworkCredential().Password
        $arguments = @(
            '-N',
            '-L', "127.0.0.1:${LocalPort}:127.0.0.1:3306",
            '-p', $SshPort,
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=30',
            '-o', 'ServerAliveCountMax=3',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'NumberOfPasswordPrompts=1',
            "$($credential.UserName)@$Server"
        )
        $process = Start-Process -FilePath 'ssh.exe' -ArgumentList $arguments -PassThru -WindowStyle Hidden

        $listener = $null
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            Start-Sleep -Milliseconds 250
            $listener = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $LocalPort -State Listen -ErrorAction SilentlyContinue
            if ($listener) {
                break
            }
            if ($process.HasExited) {
                throw "SSH tunnel exited with code $($process.ExitCode)."
            }
        }

        if (-not $listener) {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
            throw "SSH tunnel did not listen on 127.0.0.1:$LocalPort."
        }

        return [pscustomobject]@{
            LocalHost = '127.0.0.1'
            LocalPort = $LocalPort
            ProcessId = $process.Id
            AlreadyRunning = $false
        }
    } finally {
        $env:SSH_ASKPASS = $previousAskPass
        $env:SSH_ASKPASS_REQUIRE = $previousAskPassRequire
        $env:DISPLAY = $previousDisplay
        $env:EDMS_SSH_PASSWORD = $previousPassword
        Remove-Item -LiteralPath $askPass.Directory -Recurse -Force -ErrorAction SilentlyContinue
    }
}
