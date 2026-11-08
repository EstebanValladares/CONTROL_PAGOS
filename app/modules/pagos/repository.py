"""
Repositorio de datos para el dominio de Pagos y Filtros.
"""
from typing import Tuple, List, Dict, Any
from app.core.database import obtener_conexion

class PagosRepository:
    @staticmethod
    def obtener_filtros_disponibles(empresa: str) -> Dict[str, Any]:
        where_emp = ""
        if empresa == "VELAS":
            where_emp = "WHERE PROPIETARIO = 'GVDC'"
        elif empresa == "ARYVE":
            where_emp = "WHERE PROPIETARIO IN ('ARYVE', 'Aryve')"

        conn = obtener_conexion(read_only=True)
        cursor = conn.cursor()

        cursor.execute(f"SELECT DISTINCT [ENVIADO POR] FROM pagos {where_emp} AND [ENVIADO POR] IS NOT NULL AND [ENVIADO POR] != '' ORDER BY [ENVIADO POR];" if where_emp else "SELECT DISTINCT [ENVIADO POR] FROM pagos WHERE [ENVIADO POR] IS NOT NULL AND [ENVIADO POR] != '' ORDER BY [ENVIADO POR];")
        responsables = [r[0] for r in cursor.fetchall()]

        cursor.execute(f"SELECT DISTINCT ZONA FROM pagos {where_emp} AND ZONA IS NOT NULL AND ZONA != '' ORDER BY ZONA;" if where_emp else "SELECT DISTINCT ZONA FROM pagos WHERE ZONA IS NOT NULL AND ZONA != '' ORDER BY ZONA;")
        plazas = [r[0] for r in cursor.fetchall()]

        cursor.execute(f"SELECT DISTINCT FRACCIONAMIENTO FROM pagos {where_emp} AND FRACCIONAMIENTO IS NOT NULL AND FRACCIONAMIENTO != '' ORDER BY FRACCIONAMIENTO;" if where_emp else "SELECT DISTINCT FRACCIONAMIENTO FROM pagos WHERE FRACCIONAMIENTO IS NOT NULL AND FRACCIONAMIENTO != '' ORDER BY FRACCIONAMIENTO;")
        fraccionamientos = [r[0] for r in cursor.fetchall()]

        cursor.execute(f"SELECT DISTINCT RUBRO FROM pagos {where_emp} AND RUBRO IS NOT NULL AND RUBRO != '' ORDER BY RUBRO;" if where_emp else "SELECT DISTINCT RUBRO FROM pagos WHERE RUBRO IS NOT NULL AND RUBRO != '' ORDER BY RUBRO;")
        rubros = [r[0] for r in cursor.fetchall()]

        cursor.execute(f"SELECT DISTINCT SEMANA FROM pagos {where_emp} AND SEMANA IS NOT NULL AND SEMANA != '' ORDER BY SEMANA DESC;" if where_emp else "SELECT DISTINCT SEMANA FROM pagos WHERE SEMANA IS NOT NULL AND SEMANA != '' ORDER BY SEMANA DESC;")
        semanas = [r[0] for r in cursor.fetchall()]

        cursor.execute(f"SELECT COUNT(*), SUM(IMPORTE), SUM([PAGO A]), SUM(DIFERENCIA) FROM pagos {where_emp};" if where_emp else "SELECT COUNT(*), SUM(IMPORTE), SUM([PAGO A]), SUM(DIFERENCIA) FROM pagos;")
        totales = cursor.fetchone()

        semana_actual = None
        try:
            cursor.execute("""
                SELECT sem, year, fecha_inicio, fecha_final, etiqueta
                FROM cat_semanas_ek9
                WHERE fecha_inicio <= date('now', 'localtime')
                  AND fecha_final >= date('now', 'localtime')
                LIMIT 1;
            """)
            r_sem = cursor.fetchone()
            if r_sem:
                semana_actual = {
                    "etiqueta": r_sem[4],
                    "sem": r_sem[0],
                    "year": r_sem[1],
                    "fecha_inicio": r_sem[2],
                    "fecha_final": r_sem[3]
                }
        except Exception:
            pass

        conn.close()
        return {
            "responsables": responsables,
            "plazas": plazas,
            "fraccionamientos": fraccionamientos,
            "rubros": rubros,
            "semanas": semanas,
            "semana_actual": semana_actual,
            "kpis_globales": {
                "total_registros": totales[0] or 0,
                "total_importe": round(totales[1] or 0, 2),
                "total_pagando": round(totales[2] or 0, 2),
                "total_diferencia": round(totales[3] or 0, 2)
            }
        }

    @staticmethod
    def consultar_pagos_paginados(params: dict) -> Tuple[int, float, float, float, List[dict]]:
        empresa = params.get("empresa", "")
        busqueda = params.get("busqueda", "")
        responsable = params.get("responsable", "")
        plaza = params.get("plaza", "")
        fraccionamiento = params.get("fraccionamiento", "")
        rubro = params.get("rubro", "")
        semana = params.get("semana", "")
        limite = params.get("limite", 100)
        offset = params.get("offset", 0)

        condiciones = []
        valores = []

        if empresa == "VELAS":
            condiciones.append("PROPIETARIO = 'GVDC'")
        elif empresa == "ARYVE":
            condiciones.append("PROPIETARIO IN ('ARYVE', 'Aryve')")

        if busqueda:
            condiciones.append("([O.C.] LIKE ? OR CONTRATISTA LIKE ? OR OBRA LIKE ? OR [CONCEPTO] LIKE ?)")
            patron = f"%{busqueda}%"
            valores.extend([patron, patron, patron, patron])

        if responsable and responsable.upper() not in ["ADMIN", "TODOS"]:
            condiciones.append("([ENVIADO POR] = ? OR [ENVIADO POR] LIKE ?)")
            valores.extend([responsable, f"%{responsable}%"])

        if plaza:
            condiciones.append("ZONA = ?")
            valores.append(plaza)

        if fraccionamiento:
            condiciones.append("FRACCIONAMIENTO = ?")
            valores.append(fraccionamiento)

        if rubro:
            condiciones.append("RUBRO = ?")
            valores.append(rubro)

        if semana:
            condiciones.append("SEMANA = ?")
            valores.append(semana)

        where_clause = ("WHERE " + " AND ".join(condiciones)) if condiciones else ""
        
        conn = obtener_conexion(read_only=True)
        cursor = conn.cursor()

        sql_totales = f"SELECT COUNT(*), SUM(IMPORTE), SUM([PAGO A]), SUM(DIFERENCIA) FROM pagos {where_clause};"
        cursor.execute(sql_totales, valores)
        res_totales = cursor.fetchone()
        
        total_coincidencias = res_totales[0] or 0
        suma_importe = round(res_totales[1] or 0, 2)
        suma_pagando = round(res_totales[2] or 0, 2)
        suma_diferencia = round(res_totales[3] or 0, 2)

        sql_datos = f"""
            SELECT id_fila, OBRA, [NOMBRE OBRA], [O.C.], CONTRATISTA, RUBRO, 
                   IMPORTE, [PAGO A], DIFERENCIA, [ENVIADO POR], ZONA, FRACCIONAMIENTO, SEMANA,
                   ESTATUS, CONCILIADO, [CLAVE PROV], [ID PROV]
            FROM pagos {where_clause}
            ORDER BY id_fila DESC
            LIMIT ? OFFSET ?;
        """
        cursor.execute(sql_datos, list(valores) + [limite, offset])
        filas = [dict(r) for r in cursor.fetchall()]
        conn.close()

        return total_coincidencias, suma_importe, suma_pagando, suma_diferencia, filas

    @staticmethod
    def actualizar_estatus_conciliacion(id_fila: int, conciliado: bool) -> bool:
        from app.core.database import transaccion
        estatus = "APROBADO" if conciliado else "PENDIENTE"
        val_conciliado = 1 if conciliado else 0
        with transaccion() as conn:
            cursor = conn.cursor()
            cursor.execute("UPDATE pagos SET CONCILIADO = ?, ESTATUS = ? WHERE id_fila = ?;", (val_conciliado, estatus, id_fila))
            return cursor.rowcount > 0

    @staticmethod
    def crear_programacion_pago(datos: Dict[str, Any]) -> int:
        from app.core.database import transaccion
        import time

        with transaccion() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT COALESCE(MAX(id_fila), 0) + 1 FROM pagos;")
            next_id = cursor.fetchone()[0]

            fecha_hoy = time.strftime("%d/%m/%Y")
            empresa = str(datos.get("empresa", "ARYVE")).strip().upper()
            propietario = "GVDC" if empresa == "VELAS" else "ARYVE"

            cursor.execute("""
                INSERT INTO pagos (
                    id_fila, OBRA, EST, "O.C.", CONTRATISTA, IMPORTE, "PAGO A",
                    RUBRO, CONCEPTO, FECHA, "ENVIADO POR", SEMANA,
                    CIA, ZONA, TIPO, PROPIETARIO, DIFERENCIA, CONCILIADO, ESTATUS
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'PROGRAMADO');
            """, (
                next_id,
                datos.get("obra", ""),
                datos.get("tipo", "OC"),
                datos.get("folio", ""),
                datos.get("proveedor", ""),
                float(datos.get("importe", 0.0) or 0.0),
                float(datos.get("pagando", 0.0) or 0.0),
                datos.get("rubro", "MATERIALES"),
                datos.get("concepto", ""),
                fecha_hoy,
                datos.get("enviado_por", ""),
                datos.get("semana", ""),
                empresa,
                datos.get("zona", ""),
                datos.get("tipo", "OC"),
                propietario,
                float(datos.get("diferencia", 0.0) or 0.0)
            ))
            return next_id


