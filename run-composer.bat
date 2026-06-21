@echo off
cd /d "%~dp0"
echo === Checking PHP and Composer ===
where php > "%~dp0setup-log.txt" 2>&1
php --version >> "%~dp0setup-log.txt" 2>&1
where composer >> "%~dp0setup-log.txt" 2>&1
composer --version >> "%~dp0setup-log.txt" 2>&1
echo. >> "%~dp0setup-log.txt"
echo === Running composer install === >> "%~dp0setup-log.txt"
composer install --no-interaction >> "%~dp0setup-log.txt" 2>&1
echo. >> "%~dp0setup-log.txt"
echo === Done === >> "%~dp0setup-log.txt"
echo exitcode=%errorlevel% >> "%~dp0setup-log.txt"
