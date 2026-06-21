$proj = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $proj
Write-Host "Directory: $proj" -ForegroundColor Cyan
Write-Host "Running composer install..." -ForegroundColor Yellow
& composer install --no-interaction
Write-Host ""
if ($LASTEXITCODE -eq 0) {
    Write-Host "SUCCESS! vendor/ installed." -ForegroundColor Green
} else {
    Write-Host "FAILED! exit code: $LASTEXITCODE" -ForegroundColor Red
}
Read-Host "Press Enter to exit"
