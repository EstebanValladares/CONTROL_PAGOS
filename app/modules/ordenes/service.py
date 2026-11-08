"""
Servicio para Órdenes de Compra y Generación de Excel.
"""
from typing import Dict, Any
from app.modules.ordenes.repository import OrdenesRepository
from app.config import get_config
from app.core.database import obtener_conexion

config = get_config()

class OrdenesService:
    @staticmethod
    def obtener_ordenes_recientes(params: dict) -> Dict[str, Any]:
        empresa = params.get("empresa", "ARYVE")
        max_fecha = OrdenesRepository.obtener_max_fecha(empresa)
        params["max_fecha"] = max_fecha

        limite = params.get("limite", 50)
        pagina = params.get("pagina", 1)
        offset = (pagina - 1) * limite
        params["offset"] = offset

        total, suma_total, filas, kpis = OrdenesRepository.consultar_ordenes_recientes(params)
        total_paginas = (total + limite - 1) // limite if total > 0 else 1

        return {
            "status": "ok",
            "empresa": empresa,
            "max_fecha": max_fecha,
            "kpis": kpis,
            "total_filtrado": total,
            "suma_total_filtrado": suma_total,
            "total_paginas": total_paginas,
            "pagina": pagina,
            "datos": filas
        }

    @staticmethod
    def generar_excel_conciliado(params: dict) -> str:
        import pandas as pd
        empresa = params.get("empresa", "").strip().upper()
        plaza = params.get("plaza", "").strip()
        semana = params.get("semana", "").strip()
        responsable = params.get("responsable", "").strip()

        conn = obtener_conexion(read_only=True)
        query = "SELECT * FROM pagos"
        conds = []
        sql_params = []

        if empresa == "VELAS":
            conds.append("PROPIETARIO = 'GVDC'")
        elif empresa == "ARYVE":
            conds.append("PROPIETARIO IN ('ARYVE', 'Aryve')")

        if plaza:
            conds.append("ZONA = ?")
            sql_params.append(plaza)

        if semana:
            conds.append("SEMANA = ?")
            sql_params.append(semana)

        if responsable and responsable.upper() not in ["ADMIN", "TODOS"]:
            conds.append("([ENVIADO POR] = ? OR [ENVIADO POR] LIKE ?)")
            sql_params.extend([responsable, f"%{responsable}%"])

        if conds:
            query += " WHERE " + " AND ".join(conds)

        df = pd.read_sql_query(query, conn, params=sql_params)
        conn.close()

        destino = str(config.ARCHIVO_EXPORT)
        with pd.ExcelWriter(destino, engine='openpyxl') as writer:
            df.to_excel(writer, sheet_name="Conciliado", index=False)

        return destino

