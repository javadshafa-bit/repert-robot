$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $dir
Write-Host "Running migrations..." -ForegroundColor Cyan
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan migrate
Write-Host "Done!" -ForegroundColor Green
Read-Host "Press Enter to close"
