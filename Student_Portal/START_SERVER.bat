@echo off
REM Starts the StudentHub site with PHP so the .php pages RUN instead of showing code.
REM 1) Open the XAMPP Control Panel and START "MySQL" first.
REM 2) Double-click this file, then open http://localhost:8000/
cd /d "%~dp0"
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
    where php >nul 2>nul
    if errorlevel 1 (
        echo Could not find C:\xampp\php\php.exe or php on your PATH.
        echo Install XAMPP, or edit this file to point to your php.exe.
        pause
        exit /b 1
    )
    set "PHP=php"
)
echo.
echo StudentHub is running at  http://localhost:8000/
echo First time? Open        http://localhost:8000/setup.php
echo Press Ctrl+C to stop.
echo.
"%PHP%" -S localhost:8000 -t "%~dp0" "%~dp0router.php"
