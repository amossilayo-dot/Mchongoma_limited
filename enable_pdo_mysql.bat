@echo off
echo ========================================
echo   POS Mchongoma - Enable PDO MySQL
echo ========================================
echo.

REM Find XAMPP php.ini location
set PHP_INI=C:\xampp\php\php.ini

if not exist "%PHP_INI%" (
    echo ERROR: XAMPP php.ini not found at %PHP_INI%
    echo Please check your XAMPP installation path
    pause
    exit /b 1
)

echo Found php.ini at: %PHP_INI%
echo.
echo Creating backup...
copy "%PHP_INI%" "%PHP_INI%.backup" >nul

echo Enabling PDO MySQL extension...

REM Enable pdo_mysql extension
powershell -Command "(gc '%PHP_INI%') -replace ';extension=pdo_mysql', 'extension=pdo_mysql' | Out-File -encoding ASCII '%PHP_INI%'"
powershell -Command "(gc '%PHP_INI%') -replace ';extension=mysqli', 'extension=mysqli' | Out-File -encoding ASCII '%PHP_INI%'"

echo.
echo ✓ PDO MySQL extension enabled!
echo ✓ MySQLi extension enabled!
echo.
echo IMPORTANT: Now you must:
echo 1. Open XAMPP Control Panel
echo 2. STOP Apache (if running)
echo 3. START Apache
echo 4. START MySQL
echo.
echo Then open: http://localhost/pos-php-mchongoma/public/index.php
echo.
pause
