# Stops the 7 processes (sd-webui, music-gen, voice-gen, video-gen, 3d-gen, ai-tools-hub,
# sd-idle-watchdog) started by start-all.ps1.
#
# Primary method: kill any process whose command line / executable path contains one of
# these service folders, regardless of whether it was tracked by PID or has an open port.
# This avoids leaving zombie processes behind even if PID tracking missed one (e.g. a
# process that never got past a startup hang and never bound its port).
# logs\running.pids and known ports are kept as secondary safety nets.
#
# Does not touch ollama or aider - those are not started by this script.

$root = $PSScriptRoot
$logs = Join-Path $root "logs"
$pidFile = Join-Path $logs "running.pids"

$serviceDirs = @('sd-webui', 'music-gen', 'voice-gen', 'video-gen', '3d-gen', 'ai-tools-hub') |
    ForEach-Object { (Join-Path $root $_).ToLower() }

Write-Host "Stopping AI generation servers..."

$killed = [System.Collections.Generic.HashSet[int]]::new()

# 1) Path-based: kill anything running from under the 6 service folders
Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | ForEach-Object {
    $cl = $_.CommandLine
    $ep = $_.ExecutablePath
    $hit = $false
    foreach ($dir in $serviceDirs) {
        if (($cl -and $cl.ToLower().Contains($dir)) -or ($ep -and $ep.ToLower().Contains($dir))) {
            $hit = $true
            break
        }
    }
    if (-not $hit -and $cl -and $cl.ToLower().Contains('sd-webui-idle-watchdog.ps1')) {
        $hit = $true
    }
    if ($hit -and -not $killed.Contains([int]$_.ProcessId)) {
        Write-Host ("  killing {0} (pid {1})" -f $_.Name, $_.ProcessId)
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        [void]$killed.Add([int]$_.ProcessId)
    }
}

# 2) Secondary: pid file recorded by start-all.ps1
if (Test-Path $pidFile) {
    Get-Content $pidFile | ForEach-Object {
        $parts = $_ -split '=', 2
        if ($parts.Length -eq 2) {
            $rootId = 0
            if ([int]::TryParse($parts[1], [ref]$rootId) -and -not $killed.Contains($rootId)) {
                Stop-Process -Id $rootId -Force -ErrorAction SilentlyContinue
            }
        }
    }
    Remove-Item -Path $pidFile -ErrorAction SilentlyContinue
}

# 3) Secondary: anything still holding the known ports
#    (8001 = ACE-Step API, launched on demand by music-gen/server.py from music-gen\ACE-Step-1.5)
Get-NetTCPConnection -LocalPort 7860, 7862, 7863, 7864, 7865, 8001, 8611 -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

Write-Host "Done."
