@echo off
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" scripts\seed.php
) else (
  echo Use the browser setup: http://localhost/outdoor-shop/scripts/seed.php
)
echo.
pause
