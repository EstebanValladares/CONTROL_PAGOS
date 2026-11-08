import sqlite3
import pyodbc
import os
import time
from datetime import datetime, timedelta

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATOS_DIR = os.path.join(BASE_DIR, "datos")
DB_PATH = os.path.join(DATOS_DIR, "ecosistema_oc.db")

conn_str_01 = (
    "Driver={SQL Anywhere 11};"
    "EngineName=especial_v9;"
    "DatabaseName=EK_ADM01_11;"
    "CommLinks=tcpip(host=10.10.202.244:2544);"
    "UID=user_write;"
    "PWD=writaryb0904##;"
)

conn_str_14 = (
    "Driver={SQL Anywhere 11};"
    "EngineName=servicio_v9;"
    "DatabaseName=EK_ADM14_11;"
    "CommLinks=tcpip(host=10.10.202.244:2546);"
    "UID=user_write;"
    "PWD=writaryb0904##;"
)

QUERY_OC_TEMPLATE = """
SELECT
    oc.cc                  AS centro_costos,
    oc.numero              AS no_oc,
    oc.fecha,
    p.nombre               AS proveedor,
    oc.total               AS importe_bruto,
    COALESCE(oc.rentencion_despues_iva, 0) AS ret_despues_iva,
    COALESCE(oc.rentencion_antes_iva, 0)   AS ret_antes_iva,
    (oc.total - COALESCE(oc.rentencion_despues_iva, 0) - COALESCE(oc.rentencion_antes_iva, 0)) AS importe_neto,
    oc.total_rec           AS total_rec,
    oc.total_fac           AS total_fac,
    oc.estatus,
    e.descripcion          AS solicito_empleado,
    oc.solicito            AS id_empleado,
    oc.ID_EK10,
    oc.comentarios
FROM DBA.so_orden_compra oc
LEFT JOIN DBA.sp_proveedores p ON oc.proveedor = p.numpro
LEFT JOIN DBA.empleados e      ON oc.solicito = e.empleado
WHERE oc.fecha >= '{fecha_desde}'
ORDER BY oc.fecha DESC, oc.numero DESC;
"""

QUERY_DET_TEMPLATE = """
SELECT 
    d.cc,
    d.numero,
    i.tipo AS id_tipo,
    ti.descripcion AS desc_tipo,
    i.grupo AS id_grupo,
    gi.descripcion AS desc_grupo,
    SUM(d.importe) AS total_imp
FROM DBA.so_orden_compra oc
JOIN DBA.so_orden_compra_det d ON oc.cc = d.cc AND oc.numero = d.numero
JOIN DBA.insumos i ON d.insumo = i.insumo
LEFT JOIN DBA.tipos_insumo ti ON i.tipo = ti.tipo_insumo
LEFT JOIN DBA.grupos_insumo gi ON i.tipo = gi.tipo_insumo AND i.grupo = gi.grupo_insumo
WHERE oc.fecha >= '{fecha_desde}'
GROUP BY d.cc, d.numero, i.tipo, ti.descripcion, i.grupo, gi.descripcion
ORDER BY d.cc, d.numero, total_imp DESC;
"""

MAPA_TIPO_BADGE = {
    1: "(1) MATERIALES",
    2: "(2) MANO DE OBRA",
    3: "(3) EQUIPO Y HERRAMIENTA",
    4: "(4) SUB CONTRATOS",
    5: "(5) INDIRECTOS",
    6: "(6) ADMINISTRATIVOS",
    7: "(7) URBANIZACIÓN",
    8: "(8) BÁSICOS",
    9: "(9) FACTURABLES"
}

def normalizar_rubro(desc_grupo, desc_tipo):
    grp = str(desc_grupo or '').strip().upper()
    tipo = str(desc_tipo or '').strip().upper()

    if not grp:
        return tipo if tipo else "GENERAL"

    grp = grp.replace("  ", " ").strip()
    return grp

def limpiar_texto_encoding(txt):
    if not txt:
        return txt
    s = str(txt).strip()
    s = s.replace(chr(239) + chr(191) + chr(189), "Ñ")
    return s

def sincronizar_ordenes(fecha_desde="2026-01-01", verbose=True):
    t0 = time.time()
    if verbose:
        print("=" * 65)
        print(f" [SINCRONIZADOR EK9/EK10 DE PRECISIÓN] Modo: Desde {fecha_desde}")
        print(f" Destino: {DB_PATH}")
        print("=" * 65)

    sqlite_conn = sqlite3.connect(DB_PATH, timeout=20.0)
    cur_sql = sqlite_conn.cursor()

    cur_sql.execute("""
        CREATE TABLE IF NOT EXISTS ordenes_compra_ek9 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa TEXT,
            centro_costos TEXT,
            numero_oc INTEGER,
            fecha TEXT,
            proveedor TEXT,
            total REAL,
            total_rec REAL DEFAULT 0.0,
            total_fac REAL DEFAULT 0.0,
            estatus TEXT,
            solicito_empleado TEXT,
            id_empleado INTEGER,
            id_ek10 TEXT,
            comentarios TEXT,
            actualizado_en TEXT,
            tipo_sugerido TEXT,
            rubro_sugerido TEXT,
            UNIQUE(empresa, centro_costos, numero_oc)
        );
    """)

    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_oc_emp_empresa ON ordenes_compra_ek9(id_empleado, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_oc_fecha ON ordenes_compra_ek9(fecha DESC);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_oc_num ON ordenes_compra_ek9(numero_oc, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_oc_ek10 ON ordenes_compra_ek9(id_ek10);")

    ahora_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    conexiones = [
        ("ARYVE", conn_str_01),
        ("VELAS", conn_str_14)
    ]

    for empresa, conn_str in conexiones:
        if verbose:
            print(f"\n--- Sincronizando {empresa} ---")
        try:
            c = pyodbc.connect(conn_str, timeout=6, readonly=True)
            cur = c.cursor()

            # 1. Obtener detalles e insumos reales
            if verbose:
                print(" -> Consultando detalle de insumos en Sybase...")
            sql_det = QUERY_DET_TEMPLATE.format(fecha_desde=fecha_desde)
            cur.execute(sql_det)
            det_rows = cur.fetchall()

            # Mapear (cc, numero) -> mayor importe tipo y grupo
            mapa_oc = {}
            for r in det_rows:
                cc, num, id_tipo, desc_tipo, id_grupo, desc_grupo, imp = r
                key = (str(cc).strip(), int(num))
                if key not in mapa_oc:
                    id_t = int(id_tipo) if id_tipo is not None else 1
                    tipo_badge = MAPA_TIPO_BADGE.get(id_t, f"({id_t}) {desc_tipo}")
                    rubro_txt = normalizar_rubro(desc_grupo, desc_tipo)
                    mapa_oc[key] = (tipo_badge, rubro_txt)

            if verbose:
                print(f" -> Detalle obtenido para {len(mapa_oc)} órdenes.")

            # 2. Obtener cabeceras con retenciones y neto
            if verbose:
                print(" -> Consultando cabeceras de órdenes en Sybase...")
            sql_oc = QUERY_OC_TEMPLATE.format(fecha_desde=fecha_desde)
            cur.execute(sql_oc)
            oc_rows = cur.fetchall()
            c.close()

            total_sincronizadas = 0
            for r in oc_rows:
                (cc, num, f_date, prov, tot_bruto, ret_desp, ret_ant, tot_neto,
                 tot_rec, tot_fac, st, sol_nom, sol_id, ek10, com) = r

                f_str = f_date.strftime("%Y-%m-%d") if f_date else None
                cc_clean = str(cc or '').strip()
                num_clean = int(num) if num is not None else 0

                # Tipo y rubro del detalle real, o fallback
                tipo_real, rubro_real = mapa_oc.get(
                    (cc_clean, num_clean),
                    ("(1) MATERIALES", "MATERIALES")
                )

                # Neto real a pagar (descontando retenciones si existen)
                monto_final = float(tot_neto or tot_bruto or 0.0)

                cur_sql.execute("""
                    INSERT INTO ordenes_compra_ek9 (
                        empresa, centro_costos, numero_oc, fecha, proveedor, total, total_rec, total_fac, estatus,
                        solicito_empleado, id_empleado, id_ek10, comentarios, actualizado_en, tipo_sugerido, rubro_sugerido
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(empresa, centro_costos, numero_oc) DO UPDATE SET
                        fecha = excluded.fecha,
                        proveedor = excluded.proveedor,
                        total = excluded.total,
                        total_rec = excluded.total_rec,
                        total_fac = excluded.total_fac,
                        estatus = excluded.estatus,
                        solicito_empleado = excluded.solicito_empleado,
                        id_empleado = excluded.id_empleado,
                        id_ek10 = excluded.id_ek10,
                        comentarios = excluded.comentarios,
                        actualizado_en = excluded.actualizado_en,
                        tipo_sugerido = excluded.tipo_sugerido,
                        rubro_sugerido = excluded.rubro_sugerido;
                """, (
                    empresa,
                    cc_clean,
                    num_clean,
                    f_str,
                    limpiar_texto_encoding(prov),
                    monto_final,
                    float(tot_rec or 0.0),
                    float(tot_fac or 0.0),
                    str(st or '').strip(),
                    limpiar_texto_encoding(sol_nom),
                    int(sol_id) if sol_id is not None else None,
                    str(ek10 or '').strip() if ek10 else None,
                    limpiar_texto_encoding(com) if com else None,
                    ahora_str,
                    tipo_real,
                    rubro_real
                ))
                total_sincronizadas += 1

            sqlite_conn.commit()
            if verbose:
                print(f" -> Éxito: {total_sincronizadas} órdenes procesadas para {empresa}.")

        except Exception as e:
            if verbose:
                print(f" [ERROR] Sincronizando {empresa}: {e}")

    sqlite_conn.close()
    if verbose:
        print(f"\n[FIN] Sincronización finalizada en {time.time() - t0:.2f}s")

def sincronizar_ordenes_rapido(dias_atras=7, verbose=False):
    """
    Sincronización incremental rápida para el demonio de background y el botón de refresco.
    """
    f_desde = (datetime.now() - timedelta(days=dias_atras)).strftime("%Y-%m-%d")
    sincronizar_ordenes(fecha_desde=f_desde, verbose=verbose)
    return {"status": "ok", "mensaje": f"Sincronización incremental desde {f_desde} completada"}

if __name__ == "__main__":
    sincronizar_ordenes(fecha_desde="2026-10-01", verbose=True)
