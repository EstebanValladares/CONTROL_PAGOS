"""
Servicio para replicar las programaciones de pago en la base de datos de BI (Microsoft SQL Server).
Servidor: 10.30.0.2 / BD: Aryve2 / Tabla: dbo._pagos_programados
"""
import os
import time
import pyodbc
from typing import List, Dict, Any
from app.config import get_config

config = get_config()

class BIService:
    @staticmethod
    def _obtener_conexion():
        """Retorna una conexión activa a SQL Server BI (Aryve2)."""
        host = getattr(config, "BI_HOST", os.getenv("BI_HOST", "10.30.0.2"))
        port = getattr(config, "BI_PORT", os.getenv("BI_PORT", 1433))
        database = getattr(config, "BI_DB", os.getenv("BI_DB", "Aryve2"))
        user = getattr(config, "BI_USER", os.getenv("BI_USER", "sa"))
        password = getattr(config, "BI_PASS", os.getenv("BI_PASS", "Aryve2025*"))

        conn_str = (
            f"DRIVER={{SQL Server}};"
            f"SERVER={host},{port};"
            f"DATABASE={database};"
            f"UID={user};"
            f"PWD={password};"
        )
        return pyodbc.connect(conn_str, timeout=10)

    @classmethod
    def insertar_pago(cls, pago: Dict[str, Any]) -> bool:
        """Inserta un registro individual de pago en dbo._pagos_programados."""
        return cls.insertar_lote([pago]) > 0

    @classmethod
    def insertar_lote(cls, pagos: List[Dict[str, Any]]) -> int:
        """
        Inserta un lote de programaciones de pago en dbo._pagos_programados de forma masiva.
        Retorna la cantidad de registros insertados exitosamente.
        """
        if not pagos:
            return 0

        query = """
            INSERT INTO dbo._pagos_programados (
                empresa, centro_costos, numero_oc, id_ek10, proveedor,
                total_oc, monto_programado, semana, rubro, tipo, concepto,
                creado_por, creado_en
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
        """

        filas = []
        ahora_sql = time.strftime("%Y-%m-%d %H:%M:%S")

        for p in pagos:
            filas.append((
                str(p.get("empresa", "ARYVE")).strip().upper(),
                str(p.get("centro_costos", "")).strip(),
                int(p.get("numero_oc", 0)),
                str(p.get("id_ek10", "")).strip() or None,
                str(p.get("proveedor", "")).strip() or None,
                float(p.get("total_oc", 0.0) or 0.0),
                float(p.get("monto_programado", 0.0) or 0.0),
                str(p.get("semana", "")).strip(),
                str(p.get("rubro", "")).strip() or None,
                str(p.get("tipo", "")).strip() or None,
                str(p.get("concepto", "")).strip() or None,
                str(p.get("creado_por", "USUARIO")).strip(),
                p.get("creado_en") or ahora_sql
            ))

        try:
            conn = cls._obtener_conexion()
            cur = conn.cursor()
            cur.fast_executemany = True
            cur.executemany(query, filas)
            conn.commit()
            total = len(filas)
            conn.close()
            print(f" [BI SQL Server] {total} pago(s) insertados en dbo._pagos_programados con éxito.")
            return total
        except Exception as e:
            print(f" [BI SQL Server ERROR] No se pudo guardar en dbo._pagos_programados: {e}")
            return 0

    @classmethod
    def eliminar_abono(cls, empresa: str, centro_costos: str, numero_oc: int, monto: float = None, semana: str = None) -> bool:
        """Elimina un abono de dbo._pagos_programados."""
        try:
            conn = cls._obtener_conexion()
            cur = conn.cursor()
            conds = ["empresa = ?", "centro_costos = ?", "numero_oc = ?"]
            params = [empresa, centro_costos, numero_oc]

            if monto is not None:
                conds.append("ABS(monto_programado - ?) < 0.01")
                params.append(float(monto))
            if semana:
                conds.append("semana = ?")
                params.append(semana)

            sql = f"DELETE FROM dbo._pagos_programados WHERE {' AND '.join(conds)};"
            cur.execute(sql, params)
            conn.commit()
            conn.close()
            print(f" [BI SQL Server] Abono eliminado de dbo._pagos_programados: OC #{numero_oc}")
            return True
        except Exception as e:
            print(f" [BI SQL Server ERROR] No se pudo eliminar de dbo._pagos_programados: {e}")
            return False

    @classmethod
    def sincronizar_historico(cls) -> int:
        """
        Sincroniza todos los registros de pagos existentes en SQLite local hacia dbo._pagos_programados en BI.
        Evita duplicados comprobando empresa, centro_costos, numero_oc, semana y monto_programado.
        """
        from app.core.database import obtener_conexion as obtener_sqlite
        
        try:
            from app.core.database import asegurar_estado_envio_programaciones
            asegurar_estado_envio_programaciones()
            sqlite_conn = obtener_sqlite(read_only=True)
            cur_sq = sqlite_conn.cursor()
            cur_sq.execute("""
                SELECT empresa, centro_costos, numero_oc, id_ek10, proveedor,
                       total_oc, monto_programado, semana, rubro, tipo, concepto,
                       creado_por, creado_en
                FROM programaciones_oc
                WHERE estado_envio = 'ENVIADO';
            """)
            locales = [dict(r) for r in cur_sq.fetchall()]
            sqlite_conn.close()

            if not locales:
                return 0

            # Consultar lo que ya existe en BI para no duplicar
            bi_conn = cls._obtener_conexion()
            bi_cur = bi_conn.cursor()
            bi_cur.execute("""
                SELECT empresa, centro_costos, numero_oc, semana, monto_programado
                FROM dbo._pagos_programados;
            """)
            existentes = set(
                (str(r[0]).strip().upper(), str(r[1]).strip(), int(r[2]), str(r[3]).strip(), round(float(r[4]), 2))
                for r in bi_cur.fetchall()
            )

            nuevos = []
            for p in locales:
                clave = (
                    str(p.get("empresa", "ARYVE")).strip().upper(),
                    str(p.get("centro_costos", "")).strip(),
                    int(p.get("numero_oc", 0)),
                    str(p.get("semana", "")).strip(),
                    round(float(p.get("monto_programado", 0.0)), 2)
                )
                if clave not in existentes:
                    nuevos.append(p)

            if nuevos:
                cls.insertar_lote(nuevos)
                print(f" [BI SQL Server] Histórico sincronizado: {len(nuevos)} nuevos registros.")
            else:
                print(" [BI SQL Server] Histórico al día, sin registros pendientes.")

            bi_conn.close()
            return len(nuevos)
        except Exception as e:
            print(f" [BI SQL Server ERROR Sincronización Histórico] {e}")
            return 0
