# Starts the AI generation servers fully hidden (no console window at all).
# Each server's stdout/stderr is redirected to logs\<name>.out.log / .err.log,
# so if something goes wrong, check the log file instead of a popup console.
#
# "< NUL" on the cmd.exe launches: if the batch script ever hits a `pause` or similar
# prompt, redirecting stdin from NUL makes it return immediately instead of hanging
# forever with no console attached to answer it.
#
# The PID of each launched process is appended to logs\running.pids for reference;
# stop-all.ps1 does NOT rely solely on this (see its own comments) since it is not
# 100% reliable for every launch, and instead primarily matches processes by their
# working directory / command line.

$root = $PSScriptRoot
$logs = Join-Path $root "logs"
New-Item -ItemType Directory -Force -Path $logs | Out-Null

$pidFile = Join-Path $logs "running.pids"
Remove-Item -Path $pidFile -ErrorAction SilentlyContinue

function Start-Hidden {
    param(
        [string]$Name,
        [string]$WorkingDirectory,
        [string]$FilePath,
        [string]$Arguments
    )
    $out = Join-Path $logs "$Name.out.log"
    $err = Join-Path $logs "$Name.err.log"
    try {
        $p = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory `
            -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err -PassThru -ErrorAction Stop
        "$Name=$($p.Id)" | Add-Content -Path $pidFile
        Write-Host "[$Name] started pid=$($p.Id) (log: $out / $err)"
    } catch {
        Write-Host "[$Name] FAILED to start: $($_.Exception.Message)"
    }
}

Write-Host "Starting all AI generation servers in background (hidden, no console windows)..."

Start-Hidden -Name "sd-webui"     -WorkingDirectory (Join-Path $root "sd-webui")     -FilePath "cmd.exe" -Arguments "/c webui-user.bat < NUL"
Start-Hidden -Name "music-gen"    -WorkingDirectory (Join-Path $root "music-gen")    -FilePath "cmd.exe" -Arguments "/c run.bat < NUL"
Start-Hidden -Name "voice-gen"    -WorkingDirectory (Join-Path $root "voice-gen")    -FilePath "cmd.exe" -Arguments "/c run.bat < NUL"
# video-gen is disabled for now (see COMMERCIAL_SWAP_TODO.md, step 7) - AIHUB_VIDEO_ENABLED=1 to test it.
# Start-Hidden -Name "video-gen"    -WorkingDirectory (Join-Path $root "video-gen")    -FilePath "cmd.exe" -Arguments "/c run.bat < NUL"
Start-Hidden -Name "3d-gen"       -WorkingDirectory (Join-Path $root "3d-gen")       -FilePath "cmd.exe" -Arguments "/c run.bat < NUL"
Start-Hidden -Name "ai-tools-hub" -WorkingDirectory (Join-Path $root "ai-tools-hub") -FilePath "powershell.exe" -Arguments "-NoProfile -ExecutionPolicy Bypass -File serve.ps1"
Start-Hidden -Name "sd-idle-watchdog" -WorkingDirectory $root -FilePath "powershell.exe" -Arguments "-NoProfile -ExecutionPolicy Bypass -File sd-webui-idle-watchdog.ps1"

Write-Host ""
Write-Host "All started. First run may take 1-2 minutes to load models."
Write-Host "Check http://127.0.0.1:8611/ - Server Status tab."
Write-Host "Logs: $logs"
Write-Host "Stop everything with stop-all.bat"
