@echo off
setlocal
title StudentHub Portal - MySQL Integration Tests

echo =========================================================
echo   StudentHub Portal - Running MySQL Integration Tests
echo =========================================================
echo.

set "PHP_CMD=php"

where php >nul 2>&1
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_CMD=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP was not found in PATH or at C:\xampp\php\php.exe.
        echo Please ensure XAMPP is installed or PHP is in your system PATH.
        pause
        exit /b 1
    )
)

"%PHP_CMD%" "%~dp0tests\run_tests.php"
set "EXIT_CODE=%ERRORLEVEL%"

echo.
if %EXIT_CODE% equ 0 (
    echo [SUCCESS] All integration tests executed and passed.
) else (
    echo [WARNING] One or more integration tests reported an error.
)

echo.
pause
exit /b %EXIT_CODE%
