$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $dir

Write-Host "=== Git Setup ===" -ForegroundColor Cyan

git init
git branch -M main
git add .
git commit -m "initial commit"
git remote add origin https://github.com/javadshafa-bit/repert-robot.git
git push -u origin main

Write-Host "`n✅ پروژه با موفقیت روی GitHub آپلود شد!" -ForegroundColor Green
Read-Host "Press Enter to close"
