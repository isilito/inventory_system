<#
  make_backup.ps1 - copies the complete PartsBin system to a flash drive.

  Run it once per flash drive, for example (E: and F: are the drives):
      powershell -ExecutionPolicy Bypass -File presentation\make_backup.ps1 -Destination E:\
      powershell -ExecutionPolicy Bypass -File presentation\make_backup.ps1 -Destination F:\

  What ends up on the drive, in a folder named PartsBin_Backup_<date-time>:
      partsbin\                 full source code (ready to copy into C:\xampp\htdocs\)
      partsbin_source.zip       the same folder as one zip file
      partsbin_database.sql     export of the live database (only if MySQL is running)
      RESTORE_INSTRUCTIONS.txt  how to run it on another computer

  The .git folder is skipped. The .env file (holds the Gemini API key) is
  skipped unless you add -IncludeEnv. Without it the AI chat won't work from
  the backup, but everything else will.
#>
param(
    [Parameter(Mandatory = $true)][string]$Destination,
    [switch]$IncludeEnv
)

$ErrorActionPreference = 'Stop'

$root   = Split-Path -Parent $PSScriptRoot
$stamp  = Get-Date -Format 'yyyy-MM-dd_HHmm'
$target = Join-Path $Destination "PartsBin_Backup_$stamp"
$code   = Join-Path $target 'partsbin'

if (-not (Test-Path $Destination)) {
    throw "Destination '$Destination' not found. Is the flash drive plugged in?"
}

Write-Host "Copying source code from $root"
Write-Host "                     to $code"
New-Item -ItemType Directory -Force $code | Out-Null

$excludeFiles = @()
if (-not $IncludeEnv) { $excludeFiles += '.env' }

$roboArgs = @($root, $code, '/E', '/XD', (Join-Path $root '.git'), '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
if ($excludeFiles.Count -gt 0) { $roboArgs += '/XF'; $roboArgs += $excludeFiles }
& robocopy @roboArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

# ---- Check the copy is complete ----
$sourceFiles = Get-ChildItem $root -Recurse -File -Force |
    Where-Object { $_.FullName -notlike (Join-Path $root '.git') + '*' -and ($IncludeEnv -or $_.Name -ne '.env') }
$copiedFiles = Get-ChildItem $code -Recurse -File -Force
Write-Host ("Files: {0} in project, {1} copied" -f $sourceFiles.Count, $copiedFiles.Count)
if ($sourceFiles.Count -ne $copiedFiles.Count) {
    Write-Warning 'File counts differ - check the drive has enough free space.'
}

# ---- Zip (includes hidden files such as .htaccess) ----
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = Join-Path $target 'partsbin_source.zip'
[System.IO.Compression.ZipFile]::CreateFromDirectory($code, $zip, 'Optimal', $true)
Write-Host "Zip created: $zip"

# ---- Database export (optional, needs MySQL running in XAMPP) ----
$mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe'
$dumpFile  = Join-Path $target 'partsbin_database.sql'
if (Test-Path $mysqldump) {
    $errFile = [System.IO.Path]::GetTempFileName()
    $proc = Start-Process -FilePath $mysqldump -NoNewWindow -Wait -PassThru `
        -ArgumentList @('-u', 'root', '--databases', 'partsbin', '--routines', "--result-file=`"$dumpFile`"") `
        -RedirectStandardError $errFile
    Remove-Item $errFile -ErrorAction SilentlyContinue
    if ($proc.ExitCode -eq 0) {
        Write-Host "Database exported: $dumpFile"
    } else {
        Write-Warning 'Database not exported (is MySQL started in XAMPP?). The app can rebuild it from database.sql anyway.'
        Remove-Item $dumpFile -ErrorAction SilentlyContinue
    }
} else {
    Write-Warning "mysqldump not found at $mysqldump - database not exported."
}

# ---- Restore instructions ----
$envNote = if ($IncludeEnv) { 'The .env file (Gemini API key) is included - keep this drive private.' }
           else { 'The .env file is NOT included. To use the AI chat, copy .env.example to .env and paste the API key.' }

@"
PartsBin - restore on another computer
======================================

1. Install XAMPP. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy the "partsbin" folder into C:\xampp\htdocs\
   (or unzip partsbin_source.zip there).
3. Open http://localhost/partsbin/
   On the first visit the app creates the database and sample data by itself.
   Demo login: admin / admin123

Optional - restore the exact data from the backup instead of fresh sample data:
   phpMyAdmin -> Import -> choose partsbin_database.sql -> Go
   (drop the existing partsbin database first if one exists)

AI assistant: $envNote
Also set PYTHON_BIN at the bottom of config.php to that computer's python.exe,
and run:  pip install google-genai python-dotenv

Backup made: $(Get-Date -Format 'yyyy-MM-dd HH:mm')
"@ | Set-Content -Path (Join-Path $target 'RESTORE_INSTRUCTIONS.txt') -Encoding UTF8

Write-Host ''
Write-Host "Done. Backup is in $target" -ForegroundColor Green
