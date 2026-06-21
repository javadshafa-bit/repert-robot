$phpIni = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini"

if (-not (Test-Path $phpIni)) {
    Write-Host "ERROR: php.ini not found at $phpIni" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

$content = Get-Content $phpIni -Raw

# Check current state
if ($content -match '^extension=zip\s*$') {
    Write-Host "extension=zip is already enabled!" -ForegroundColor Green
} elseif ($content -match '^;extension=zip\s*$') {
    # Uncomment it
    $content = $content -replace '(?m)^;extension=zip\s*$', 'extension=zip'
    Set-Content $phpIni $content -NoNewline
    Write-Host "Enabled extension=zip in php.ini" -ForegroundColor Green
} else {
    # Add it after [PHP] section or at end of extensions block
    $content = $content -replace '(?m)^;extension=gd\s*$', "extension=zip`r`n;extension=gd"
    if ($content -match 'extension=zip') {
        Set-Content $phpIni $content -NoNewline
        Write-Host "Added extension=zip to php.ini" -ForegroundColor Green
    } else {
        # Just append it
        Add-Content $phpIni "`r`nextension=zip"
        Write-Host "Appended extension=zip to php.ini" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Verifying..." -ForegroundColor Cyan
$result = & "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -m 2>&1
if ($result -match 'zip') {
    Write-Host "zip extension is ACTIVE!" -ForegroundColor Green
} else {
    Write-Host "zip extension NOT found in php -m output. Check php.ini manually." -ForegroundColor Red
    Write-Host "Lines containing 'zip' in php.ini:"
    Get-Content $phpIni | Select-String 'zip'
}

Read-Host "Press Enter to exit"
