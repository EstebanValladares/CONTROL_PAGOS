# 📘 Manual de Usuario: Control de Pagos y Órdenes de Compra
*Plataforma Institucional para la Operación, Conciliación Semanal y Seguimiento de Órdenes de Compra — Grupo Velas & Construcciones Aryve.*

---

## 🧭 1. Resumen Ejecutivo y Propósito

> **¿Qué es esta plataforma?**  
> El sistema **Control de Pagos OC** es una herramienta centralizada diseñada para los equipos de administración, supervisión de obra y finanzas. Permite consultar, conciliar y validar en tiempo real los pagos aplicados contra las órdenes de compra emitidas en el ERP **EnKontrol (EK9 / EK10)**, garantizando transparencia operativa y agilizando el flujo de caja semanal.

---

## 🔐 2. Acceso al Sistema (Inicio de Sesión)

Para ingresar a la plataforma, navega a la URL institucional (ejemplo: `http://localhost:5000` o la IP de tu servidor interno).

### 📋 Campos del Formulario de Ingreso
- **🏢 Empresa / Grupo:** `CONSTRUCCIONES ARYVE` o `GRUPO VELAS`.
- **👤 Usuario, No. de Empleado o Correo:** Tu identificador registrado en el ERP EnKontrol (`120`, `3399`, etc.).
- **🔑 Contraseña de Acceso:** Tu clave personal de acceso a EnKontrol.

---

## 📑 3. Vista Principal: Módulo de Órdenes de Compra

Esta es la pantalla que visualizan los usuarios al ingresar a la plataforma para el **rastreo de órdenes de compra solicitadas, su equivalencia entre sistemas (EK10 vs EK9) y su estado de autorización en tiempo real**.

### 1️⃣ Barra Superior (Header Institucional)
- **🏢 Badge de Empresa (`CONSTRUCCIONES ARYVE` / `GRUPO VELAS`):** Indica la base de datos empresarial activa.
- **👤 Perfil de Usuario Activo:** Muestra el nombre completo del colaborador autenticado (ejemplo: *ELIZABETH BALLEZA OLARTE*), su plaza operativa (*📍 Tampico*) y el botón de salida segura `[→]` para cerrar sesión.

### 2️⃣ Tarjeta Izquierda: Indicador "Órdenes Hoy"
- **🟢 Punto Verde de Conectividad:** Confirma que la réplica local está en sincronía con el servidor central de EnKontrol.
- **Contador de Órdenes Hoy:** Cantidad de órdenes emitidas durante el día en curso.
- **Monto Total del Día ($):** Importe monetario acumulado de las órdenes del día.

### 3️⃣ Tarjeta Derecha: "Datos del Usuario" (Sesión Activa)
- **Nombre del Colaborador:** Nombre oficial en Recursos Humanos.
- **🏷️ ID Empleado:** Número de nómina único (ejemplo: `# 988`), utilizado para ligar qué compras le pertenecen.
- **💼 Cargo Oficial del Puesto:** Puesto laboral oficial registrado en el catálogo maestro de EnKontrol (ejemplo: `💼 SUPERVISOR DE OBRA`, `💼 RESIDENTE DE OBRA`, `💼 COMPRADOR`, etc.), reemplazando los códigos numéricos por el nombre oficial del cargo.
- **📍 Plaza / Región:** Zona geográfica de adscripción (ejemplo: *Tampico*).
- **✉️ Correo Institucional:** Dirección corporativa asociada.
- **Filtro Rápido `👤 Mis Órdenes`:** Filtra únicamente las órdenes donde el usuario aparece como solicitante. Los administradores pueden desactivarlo para auditar órdenes globales.

### 4️⃣ Barra de Filtros Temporales y Búsqueda en Vivo
- **• Hoy:** Filtra compras registradas exclusivamente en la fecha actual.
- **Últimos 7 Días:** Muestra movimientos de la última semana hábil.
- **Este Mes:** Acumulado del primer día del mes actual a la fecha.
- **Todas (2026):** Histórico consolidado del ejercicio fiscal.
- **🔍 Buscador Universal:** Permite buscar por **# O.C.**, **Folio EK10**, **Nombre de Proveedor** o **Obra / C.C.** Presiona `Enter` para filtrar.
- **🔄 Refrescar EK10:** Consulta directamente al ERP para traer compras recién capturadas.

### 5️⃣ Matriz de "Órdenes de Compra Recientes"
*Resumen superior: Mostrando 15 de 26 órdenes encontradas (Suma total: $3,454,150.57).*

| Columna | Significado e Importancia Operativa |
| :--- | :--- |
| **OBRA** | Código del Centro de Costos / Proyecto (ej. `AP5`, `SELVA`, `ARECAS`). Identifica a qué desarrollo se carga el costo. |
| **OC** | **Número Oficial de Orden en EK9** (ej. `#256`). Identificador contable para almacén y tesorería. |
| **FOLIO EK10 - EK9** | **Equivalencia de Sistemas:** Muestra la relación entre la orden web EK10 y su réplica en EK9 (`Solo EK9: #256` o `EK10-XXXX ➔ #256`). |
| **FECHA** | Día en que fue elaborada y sellada la orden (`AAAA-MM-DD`). |
| **PROVEEDOR / CONTRATISTA** | Razón social adjudicada (ej. *CONSTRUCTORA TANTOS KYA SA DE CV*). |
| **IMPORTE ($)** | Costo total de la orden con impuestos incluidos. |
| **SOLICITÓ** | Nombre del empleado o residente que originó la requisición. |
| **ESTATUS** | Estado en EnKontrol: **`Activa` (Verde)**, **`Tránsito` (Ámbar)** o **`Cancelada` (Rojo)**. |
| **COMENTARIOS** | Observaciones de obra o condiciones de pago (ej. *TRANSFERENCIA PU*). |

---

## 📊 4. Módulo de Conciliación Semanal de Pagos

*(Vista exclusiva para usuarios con rol administrativo y supervisores de pago)*
- **Filtros por Semana:** Localización por semana operativa (ej. `SEM: 42-2026`).
- **Conciliación en 1 Clic:** Marcado directo en la casilla de verificación (**Conc.**) cambiando inmediatamente el estatus a **`APROBADO`**.
- **Descargar Excel:** Exportación del concentrado semanal para tesorería y contabilidad.


## 📊 4. Módulo de Conciliación Semanal de Pagos

*(Vista exclusiva para usuarios con rol administrativo y supervisores de pago)*
- **Filtros por Semana:** Localización por semana operativa (ej. `SEM: 42-2026`).
- **Conciliación en 1 Clic:** Marcado directo en la casilla de verificación (**Conc.**) cambiando inmediatamente el estatus a **`APROBADO`**.
- **Descargar Excel:** Exportación del concentrado semanal para tesorería y contabilidad.
---