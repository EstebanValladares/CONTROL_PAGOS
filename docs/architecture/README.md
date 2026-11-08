# Arquitectura del Sistema

Para el flujo detallado de datos desde Enkontrol, las tablas consultadas, sus relaciones y la distribución hacia las vistas, consulta [Flujo de datos y tablas](./flujo-datos-y-tablas.md).

## 1. Visión General y Principios
El sistema adopta una arquitectura orientada a características (**Feature-Driven / Modular Architecture**) complementada con principios de **Clean Architecture**:

```
[ Cliente Web: Vue 3 + Tailwind CSS ]
                  │  (Peticiones HTTP JSON)
                  ▼
         [ Flask Blueprints ]
                  │
        ┌─────────┴─────────┐
        ▼                   ▼
 [ Domain Services ]   [ MotorHashCache (RAM O(1)) ]
        │                   │
        ▼                   │ (Miss)
 [ Repositorios SQL ] <─────┘
        │
        ▼
 [ SQLite (WAL / B-Tree) ] <──── Sincronización Remota ──── [ Sybase SQL Anywhere (EK9) ]
```

## 2. Capas de la Aplicación

### A. Capa de Presentación (Frontend)
- **Tecnología:** Vue 3 (Composition API / CDN) + Tailwind CSS + FontAwesome.
- La interfaz activa y su estado Vue están definidos en `templates/index.html`.
- Los estilos y componentes activos se definen en la plantilla; no se usa un bundle local de `static/`.

### B. Capa de Controladores y Rutas (Flask Blueprints)
- Ubicada en `app/modules/<dominio>/routes.py`.
- Responsable únicamente de recibir peticiones HTTP, validar parámetros y retornar respuestas serializadas en formato JSON.

### C. Capa de Servicios y Negocio (Domain Services)
- Ubicada en `app/modules/<dominio>/service.py`.
- Implementa la lógica de conciliación, reglas de cálculo de saldos pendientes y validaciones de negocio.

### D. Capa de Acceso a Datos (Repositories)
- Ubicada en `app/modules/<dominio>/repository.py`.
- Encapsula las sentencias SQL hacia `ecosistema_oc.db`, evitando consultas dispersas por el código.

### E. Capa de Integración Externa
- Ubicada en `app/modules/sincronizacion/`.
- Conecta con motores Sybase SQL Anywhere mediante PyODBC, ejecutando consultas delta periódicas.
