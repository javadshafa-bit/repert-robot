# Download and install Composer for Laragon
$composerDir = "C:\laragon\usr\bin"
$phpPath = (Get-ChildItem "C:\laragon\bin\php" -Filter "php.exe" -Recurse | Select-Object -First 1).FullName

Write-Host "PHP found at: $phpPath"
Write-Host "Installing Composer to: $composerDir"

# Create directory if not exists
New-Item -ItemType Directory -Force -Path $composerDir | Out-Null

# Download composer.phar
Write-Host "Downloading composer.phar..."
Invoke-WebRequest -Uri "https://getcomposer.org/composer-stable.phar" -OutFile "$composerDir\composer.phar"

# Create composer.bat wrapper
$batContent = "@echo off`r`n`"$phpPath`" `"$composerDir\composer.phar`" %*"
Set-Content -Path "$composerDir\composer.bat" -Value $batContent

Write-Host ""
Write-Host "Composer installed! Testing..."
& "$composerDir\composer.bat" --version

# Add to system PATH if not already there
$currentPath = [Environment]::GetEnvironmentVariable("PATH", "Machine")
if ($currentPath -notlike "*$composerDir*") {
    [Environment]::SetEnvironmentVariable("PATH", "$currentPath;$composerDir", "Machine")
    Write-Host "Added $composerDir to system PATH"
}

Write-Host ""
Write-Host "Done! Composer is ready."
Write-Host "Now run setup-local.bat to set up the project."
Read-Host "Press Enter to exit"
