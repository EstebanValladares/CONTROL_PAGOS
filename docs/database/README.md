# Diccionario de Base de Datos y Fuentes

El detalle vigente de las consultas a Enkontrol, las tablas locales, sus relaciones y los diagramas de flujo está en [Flujo de datos y tablas](../architecture/flujo-datos-y-tablas.md).

## Fuentes Enkontrol

El sincronizador de órdenes consulta SQL Anywhere en las bases:

| Base | Empresa asignada |
|---|---|
| `EK_ADM01_11` | `ARYVE` — Construcciones Aryve |
| `EK_ADM14_11` | `VELAS` — Grupo Velas |

Para nombres exactos de tablas, uniones y campos consultados, véase la sección “Consulta de órdenes de compra a Enkontrol” del documento enlazado.

## SQLite de la aplicación

Por defecto, Flask usa `datos/ecosistema_oc.db`. La ruta puede cambiarse con la variable de entorno `DB_NAME`.

Tablas principales usadas por el código:

- `ordenes_compra_ek9`: réplica incremental de órdenes de Enkontrol.
- `programaciones_oc`: pagos programados desde el dashboard.
- `documentos_oc`: metadatos de documentos cuando la carga está habilitada.
- `usuarios_ek9`: usuarios sincronizados para el login.
- `pagos`: conciliación indexada desde `datos/pagos.csv`.
- `cat_semanas_ek9`: catálogo de semanas usado en filtros.

Las tablas `proveedores` e `insumos` se cargan desde sus CSV por `indexador.py`; no deben confundirse con las tablas de proveedor e insumo consultadas en las bases Sybase de Enkontrol.

> La aplicación también puede ejecutarse con otra base de pruebas mediante `FLASK_ENV=testing`. La estructura efectiva debe verificarse contra el archivo SQLite que esté configurado en el entorno.

## Esquema propuesto para BI (SQL Server)

`sqlserver_bi_schema.sql` define la tabla propuesta `dbo.so_orden_compra_programados`
para ejecutarse manualmente en la base BI con una cuenta autorizada. El script
solo crea el esquema si la tabla no existe; no se ha ejecutado desde la aplicación
ni copia registros.

La tabla contiene los campos de pagos programados de `programaciones_oc`. No incluye
archivos adjuntos ni metadatos de documentos. Antes de usar el esquema, el
administrador de BI debe aprobar el nombre y tipos de datos.
