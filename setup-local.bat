@echo off
:: Change to the directory where this bat file is located (handles Persian paths)
cd /d "%~dp0"
echo Current directory: %CD%
echo.

echo ===== Setting up Laravel project =====

echo.
echo [1/5] Installing PHP dependencies (composer install)...
composer install --no-interaction
if errorlevel 1 (
    echo ERROR: composer install failed!
    pause
    exit /b 1
)

echo.
echo [2/5] Creating SQLite database if not exists...
if not exist "database\database.sqlite" (
    type nul > "database\database.sqlite"
    echo Created database\database.sqlite
) else (
    echo database.sqlite already exists
)

echo.
echo [3/5] Running migrations...
php artisan migrate --force

echo.
echo [4/5] Installing Node dependencies (npm install)...
npm install
if errorlevel 1 (
    echo ERROR: npm install failed!
    pause
    exit /b 1
)

echo.
echo [5/5] Building assets (npm run build)...
npm run build

echo.
echo ===== Setup complete! =====
echo.
echo Starting development server at http://localhost:8000
echo Press Ctrl+C to stop the server.
echo.
php artisan serve

pause
