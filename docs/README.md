# Documentación del Proyecto CONTROL_PAGOS_OC

Bienvenido al centro de documentación técnica del sistema **Control y Conciliación de Pagos de Órdenes de Compra (Grupo Velas)**.

## 📁 Estructura Documental

- **Documentación Ejecutiva y Diagramas de Procesos (Sin Código):**
  - **[Versión PDF (`docs/DOCUMENTACION_TECNICA_PROCESOS.pdf`)](DOCUMENTACION_TECNICA_PROCESOS.pdf)** *(también disponible en la raíz del proyecto para compartir fácilmente)*
  - **[Versión Web Interactiva (`docs/DOCUMENTACION_TECNICA_PROCESOS.html`)](DOCUMENTACION_TECNICA_PROCESOS.html)**
  - **[Fuente Markdown + Mermaid (`docs/DOCUMENTACION_TECNICA_PROCESOS.md`)](DOCUMENTACION_TECNICA_PROCESOS.md)**
- **[Arquitectura (`docs/architecture/`)](architecture/README.md):**
  - Patrones de diseño (Clean Architecture, Feature Modules).
  - Flujo de datos y ciclo de vida de las solicitudes.
  - Estrategia de Caché Multinivel (`MotorHashCache` O(1) + SQLite WAL B-Tree).
- **[Catálogo de APIs (`docs/api/`)](api/README.md):**
  - Documentación de Blueprints y endpoints REST (`/api/ordenes`, `/api/pagos`, `/api/metricas`, `/api/sincronizacion`).
  - Formato de respuestas, paginación y manejo de errores.
- **[Base de Datos (`docs/database/`)](database/README.md):**
  - Diccionario de datos de la base local SQLite (`ecosistema_oc.db`).
  - Mapeo y consultas hacia Sybase SQL Anywhere (EK9).
- **[Despliegue y Operación (`docs/deployment/`)](deployment/README.md):**
  - Guía de ejecución con Docker y Docker Compose.
  - Configuración de variables de entorno (`.env`).
  - Tareas programadas y cron jobs de sincronización.

