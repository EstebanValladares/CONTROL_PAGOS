"""
Repositorio para consultas directas de Órdenes de Compra EK9 / SQLite.
"""
from typing import Tuple, List, Dict, Any
from app.core.database import obtener_conexion

class OrdenesRepository:
    @staticmethod
    def obtener_max_fecha(empresa: str) -> str:
        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        cur.execute("SELECT MAX(fecha) FROM ordenes_compra_ek9 WHERE empresa = ?;", (empresa,))
        row = cur.fetchone()
        conn.close()
        return row[0] if row and row[0] else None

    @staticmethod
    def obtener_catalogo_obras(empresa: str = "ARYVE") -> List[str]:
        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        cur.execute("""
            SELECT DISTINCT centro_costos 
            FROM ordenes_compra_ek9 
            WHERE empresa = ? AND centro_costos IS NOT NULL AND centro_costos != '' 
            ORDER BY centro_costos ASC;
        """, (empresa,))
        rows = [r[0] for r in cur.fetchall()]
        conn.close()
        return rows

    @staticmethod
    def consultar_ordenes_recientes(params: dict) -> Tuple[int, float, List[dict], dict]:
        empresa = params.get("empresa", "ARYVE")
        max_fecha = params.get("max_fecha")
        empleado_id = params.get("empleado_id", "")
        filtro_temporal = params.get("filtro_temporal", "HOY")
        filtro_semana = params.get("semana", "").strip()
        busqueda = params.get("busqueda", "")
        obra = params.get("obra", "").strip()
        mis_ordenes = params.get("mis_ordenes", True)
        limite = params.get("limite", 50)
        offset = params.get("offset", 0)

        condiciones = ["empresa = ?"]
        valores = [empresa]

        # Filtro de usuario
        if mis_ordenes and empleado_id and empleado_id != "0" and empleado_id.upper() != "ADMIN":
            try:
                condiciones.append("id_empleado = ?")
                valores.append(int(empleado_id))
            except Exception:
                pass

        # Filtro por semana (prioritario si viene seleccionada) o temporal
        if filtro_semana:
            conn_sem = obtener_conexion(read_only=True)
            cur_sem = conn_sem.cursor()
            cur_sem.execute("SELECT fecha_inicio, fecha_final FROM cat_semanas_ek9 WHERE etiqueta = ? OR sem = ? LIMIT 1;", (filtro_semana, filtro_semana))
            row_sem = cur_sem.fetchone()
            conn_sem.close()
            if row_sem and row_sem[0] and row_sem[1]:
                condiciones.append("fecha >= ? AND fecha <= ?")
                valores.extend([row_sem[0], row_sem[1]])
        elif filtro_temporal == "HOY":
            condiciones.append("fecha = date('now', 'localtime')")
        elif filtro_temporal == "SEMANA":
            condiciones.append("fecha >= date('now', '-7 days')")
        elif filtro_temporal == "MES":
            condiciones.append("fecha >= date('now', 'start of month')")

        # Búsqueda
        if obra:
            condiciones.append("centro_costos = ?")
            valores.append(obra)

        if busqueda:
            condiciones.append("(CAST(numero_oc AS TEXT) LIKE ? OR proveedor LIKE ? OR centro_costos LIKE ? OR id_ek10 LIKE ? OR comentarios LIKE ?)")
            p = f"%{busqueda}%"
            valores.extend([p, p, p, p, p])

        where_sql = "WHERE " + " AND ".join(condiciones)

        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()

        # Totales filtrados
        cur.execute(f"SELECT COUNT(*), SUM(total) FROM ordenes_compra_ek9 {where_sql};", valores)
        tot_row = cur.fetchone()
        total_coincidencias = tot_row[0] or 0
        suma_total = round(tot_row[1] or 0.0, 2)
 
        # Datos paginados
        sql_datos = f"""
            SELECT id, empresa, centro_costos, numero_oc, fecha, proveedor,
                   total, total_rec, total_fac, estatus, solicito_empleado, id_empleado, id_ek10, comentarios, actualizado_en,
                   tipo_sugerido, rubro_sugerido
            FROM ordenes_compra_ek9
            {where_sql}
            ORDER BY fecha DESC, numero_oc DESC
            LIMIT ? OFFSET ?;
        """
        cur.execute(sql_datos, list(valores) + [limite, offset])
        filas = [dict(r) for r in cur.fetchall()]

        # Consultar pagos programados asociados a cada OC para saldo dinámico
        if filas:
            for f in filas:
                cur.execute("""
                    SELECT id, monto_programado, semana, rubro, concepto, creado_por, creado_en
                    FROM programaciones_oc
                    WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                    ORDER BY id ASC;
                """, (f["empresa"], f["centro_costos"], f["numero_oc"]))
                progs = [dict(row) for row in cur.fetchall()]
                f["programaciones"] = progs
                pagado_acum = sum(p["monto_programado"] for p in progs)
                f["total_programado"] = round(pagado_acum, 2)
                f["saldo_disponible"] = max(0.0, round(float(f["total"] or 0.0) - pagado_acum, 2))

                # Documentos adjuntos a esta OC
                cur.execute("""
                    SELECT id, nombre_original, nombre_guardado, tamano_bytes, creado_en
                    FROM documentos_oc
                    WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                    ORDER BY id ASC;
                """, (f["empresa"], f["centro_costos"], f["numero_oc"]))
                docs = [dict(row) for row in cur.fetchall()]
                f["documentos"] = docs
                f["total_documentos"] = len(docs)

        # KPIs rápidos de Hoy y Acumulado
        cond_kpi = ["empresa = ?"]
        val_kpi = [empresa]
        if mis_ordenes and empleado_id and empleado_id != "0" and empleado_id.upper() != "ADMIN":
            try:
                cond_kpi.append("id_empleado = ?")
                val_kpi.append(int(empleado_id))
            except Exception:
                pass

        where_kpi = "WHERE " + " AND ".join(cond_kpi)

        cur.execute(f"""
            SELECT 
                SUM(CASE WHEN fecha = date('now', 'localtime') THEN 1 ELSE 0 END),
                SUM(CASE WHEN fecha = date('now', 'localtime') THEN total ELSE 0 END),
                COUNT(*),
                SUM(total)
            FROM ordenes_compra_ek9
            {where_kpi};
        """, val_kpi)
        kpis = cur.fetchone()
        conn.close()

        kpi_dict = {
            "ordenes_hoy": kpis[0] or 0,
            "monto_hoy": round(kpis[1] or 0.0, 2),
            "total_acumulado": kpis[2] or 0,
            "monto_acumulado": round(kpis[3] or 0.0, 2)
        }

        return total_coincidencias, suma_total, filas, kpi_dict

