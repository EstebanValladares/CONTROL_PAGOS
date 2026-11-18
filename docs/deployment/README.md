# Guía de Despliegue y Ejecución - Control de Pagos y OCs

## 1. Requisitos del Sistema
- **PHP 8.2+** (probado con PHP 8.5) con extensiones: `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`.
- **Composer 2.x**.
- **Python 3.10+** (para el sincronizador Enkontrol `sincronizar_ordenes_ek9.py` y driver ODBC SQL Anywhere 11 en red de oficina).
- **SQLite 3**.

## 2. Puesta en Marcha

1. **Variables de Entorno:**
   Verificar que `.env` esté configurado con el puerto estándar `8050` y la ruta absoluta a la base de datos `database/ecosistema_oc.db`.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. **Inicio del Sistema:**
   Ejecutar el lanzador unificado:
   ```cmd
   iniciar_sistema.bat
   ```
   O manualmente con Artisan:
   ```bash
   php artisan serve --host=127.0.0.1 --port=8050
   ```

3. **Sincronización:**
   El sincronizador `sincronizar_ordenes_ek9.py` se ejecuta automáticamente o a demanda vía API. Si no hay conexión con Enkontrol (10.10.202.244), el sistema entra en modo local/casa automáticamente.

4. **Monitoreo y Torre de Control:**
   El endpoint `/api/telemetria` entrega métricas en tiempo real consumidas por `DASHBOARD_EK10` (puerto 8000).
