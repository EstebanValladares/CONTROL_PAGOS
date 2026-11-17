@echo off
title Control y Conciliacion de Pagos OC - Laravel
cd /d "%~dp0"

echo ==============================================================================
echo       SISTEMA DE CONTROL Y CONCILIACION DE PAGOS OC - GRUPO VELAS
echo ==============================================================================
echo.
echo [*] Verificando entorno local de ejecucion...

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP no se encuentra en el PATH del sistema.
    echo Asegurate de tener PHP instalado y agregado a las variables de entorno.
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('php -v') do (
    echo [OK] %%i
    goto :php_ok
)
:php_ok

if not exist ".env" (
    echo [*] Archivo .env no encontrado. Creando copia desde .env.example...
    copy ".env.example" ".env" >nul
    call php artisan key:generate
)

if not exist "database\ecosistema_oc.db" (
    echo [AVISO] No se encontro database\ecosistema_oc.db. Creando base de datos vacia...
    type nul > "database\ecosistema_oc.db"
    call php artisan migrate --force
)

echo.
echo ==============================================================================
echo  [OK] Todo listo. Iniciando servidor en: http://localhost:8050
echo  [OK] Abriendo navegador automaticamente...
echo.
echo  Para apagar el servidor, cierra esta ventana o presiona Ctrl + C.
echo ==============================================================================
echo.

start "" cmd /c "timeout /t 2 /nobreak >nul & start http://localhost:8050"

php artisan serve --host=127.0.0.1 --port=8050

pause