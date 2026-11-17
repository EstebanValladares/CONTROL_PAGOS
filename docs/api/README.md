# Especificación de la API REST

## Resumen de Endpoints Principales

| Método | Endpoint | Módulo | Descripción |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/ordenes` | `ordenes` | Consulta paginada y filtrada de OCs con conciliación |
| `GET` | `/api/ordenes/<id>` | `ordenes` | Detalle específico de una orden de compra |
| `GET` | `/api/ordenes/filtros` | `ordenes` | Opciones únicas para selects (empresas, proveedores, estatus) |
| `POST` | `/api/ordenes/exportar` | `ordenes` | Generación y descarga del reporte Excel conciliado |
| `GET` | `/api/metricas` | `metricas` | KPIs consolidados y estadísticas del `MotorHashCache` |
| `POST` | `/api/sincronizar` | `sincronizacion` | Disparo manual o programado del sync con EK9 |
| `POST` | `/api/indexador/reindexar` | `indexador` | Reconstrucción de índices B-Tree y optimización SQLite |
| `POST` | `/api/ordenes/programar-lote` | `ordenes` | Guarda pagos pendientes localmente y sus documentos; no los envía a BI |
| `POST` | `/api/ordenes/programar-abono` | `ordenes` | Registra un abono individual después de validar el saldo real de la OC |
| `POST` | `/api/ordenes/programaciones/<id>/enviar` | `ordenes` | Envía a BI un pago pendiente de la tabla GUARDADO |
| `GET` | `/api/ordenes/seguimiento` | `seguimiento` | Monitor paginado de OCs asignadas, en limbo o sin responsable (solo Administración) |
| `GET` | `/api/ordenes/seguimiento/compradoras` | `seguimiento` | Compradoras activas disponibles para reasignar (solo rol `ADMIN`) |
| `GET` | `/api/ordenes/seguimiento/historial` | `seguimiento` | Bitácora de actividad de una OC visible para su responsable o Administración |
| `POST` | `/api/ordenes/seguimiento/nota` | `seguimiento` | Registra actividad manual en una OC asignada |
| `POST` | `/api/ordenes/seguimiento/estado` | `seguimiento` | Actualiza el estado de seguimiento con motivo |
| `POST` | `/api/ordenes/seguimiento/reasignar-cartera` | `seguimiento` | Reasigna hasta 100 OCs a una compradora activa (solo rol `ADMIN`) |

---

## Seguimiento y reasignación de OCs

El monitor guarda responsables e historial en `oc_seguimientos` y `oc_movimientos`,
tablas nuevas de la SQLite local de Laravel. No modifica `ordenes_compra_ek9`,
`usuarios_ek9`, Enkontrol ni BI. Las OCs existentes comienzan su conteo cuando se
inicializa el monitor; no se atribuye actividad histórica que la aplicación no podía
observar. Las OCs cuyo estatus de Enkontrol es `C` (Cancelada) se excluyen. La
sincronización periódica de Enkontrol no reinicia ese conteo.

Una OC entra en limbo al superar estrictamente 60 días sin una nota, cambio de estado
o actividad de pago registrada desde Control de Pagos. CERRADA y CANCELADA quedan
fuera de la alerta. La reasignación se registra en la bitácora, requiere motivo y no
reinicia el contador. Los empleados se autorizan con el ID guardado en la sesión del
servidor: la compradora solo puede actualizar sus OCs asignadas y únicamente las
sesiones cuyo rol sea `ADMIN` pueden consultar el catálogo de compradoras o reasignar
carteras. El permiso administrativo funcional no basta para reasignar.

Para instalar estas tablas en la SQLite configurada por Laravel, ejecutar desde la
carpeta `CONTROL_PAGOS_OC` únicamente esta migración:

```powershell
php artisan migrate --path=database\migrations\2026_11_17_155519_create_oc_follow_up_tables.php
```

No ejecutar `migrate:fresh` ni editar la base de Enkontrol para habilitar el monitor.

---

## Programación de pagos con documentos

`POST /api/ordenes/programar-lote` recibe `multipart/form-data`. El campo `items`
contiene el arreglo JSON de pagos; los documentos de cada pago se envían en campos
`archivos_0`, `archivos_1`, etc., usando el índice correspondiente dentro de `items`.
Cuando un pago incluye documentos, se requieren de 2 a 4 archivos.

“GUARDAR TODO” escribe cada pago en `programaciones_oc` con estado `PENDIENTE`.
Los pagos permanecen en la tabla “GUARDADO” incluso después de recargar la página o
cerrar sesión. El icono de envío de cada fila llama a
`POST /api/ordenes/programaciones/<id>/enviar`; solo entonces se replica el pago a BI
y se cambia su estado a `ENVIADO`. Si BI no confirma la inserción, el pago permanece
pendiente y la interfaz permite reintentar.

Antes de guardar, el servidor obtiene el importe de la OC desde `ordenes_compra_ek9`
y resta todas las programaciones existentes, incluidas las pendientes de envío a BI.
No confía en el `total_oc` enviado por el navegador. Si un monto supera el saldo
disponible, rechaza la solicitud y no guarda parcialmente el lote. La misma validación
se aplica al endpoint individual de abono.

La migración agrega `estado_envio` a `programaciones_oc` si aún no existe. Los
registros anteriores se consideran `ENVIADO`; los pagos nuevos se guardan como
`PENDIENTE`.

Cuando la carga está habilitada, los pagos y sus documentos se guardan juntos en la
etapa de GUARDAR TODO; la tabla muestra el indicador de documentos disponibles.

La carga de documentos está desactivada por defecto para las pruebas mediante
`DOCUMENTOS_HABILITADOS = False` en `app/config.py`. Con el valor `False`, al guardar
un pago el navegador no envía los adjuntos y el servidor no los guarda. Para habilitar
la persistencia actual, cambiar ese valor a `True` y reiniciar la aplicación. Esto no
configura FTP: el proyecto todavía no incluye integración FTP.

Antes de guardar un pago con XML CFDI, la aplicación lee localmente el atributo
`Total` de cada `Comprobante` y suma los XML seleccionados. Rechaza el pago si esa
suma es igual o mayor al importe que se programa. No se exige XML si no se adjuntó
ninguno. Con documentos desactivados, solo se envía al servidor el total calculado
para validar el pago; el archivo XML permanece en el navegador y no se guarda.

## Formato Estándar de Respuesta

### Éxito (HTTP 200 / 201)
```json
{
  "exito": true,
  "datos": [...],
  "meta": {
    "total_registros": 1250,
    "pagina": 1,
    "limite": 50,
    "tiempo_ms": 1.45,
    "fuente": "CACHE_HASH_RAM_O1"
  }
}
```

### Error (HTTP 400 / 500)
```json
{
  "exito": false,
  "error": {
    "codigo": "PARAMETRO_INVALIDO",
    "mensaje": "El rango de fechas especificado no es válido."
  }
}
```
