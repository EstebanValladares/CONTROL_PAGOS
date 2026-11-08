"""
Rutas para la telemetría del sistema, signos vitales y métricas de rendimiento.
"""
import time
from datetime import datetime
from flask import Blueprint, jsonify
from app.core.cache import cache_global
from app.core.database import obtener_conexion
import indexador

metricas_bp = Blueprint("metricas", __name__)
TIEMPO_ARRANQUE = time.time()

DIAS_ESP = {
    'Monday': 'Lunes',
    'Tuesday': 'Martes',
    'Wednesday': 'Miércoles',
    'Thursday': 'Jueves',
    'Friday': 'Viernes',
    'Saturday': 'Sábado',
    'Sunday': 'Domingo'
}

@metricas_bp.route("/api/telemetria", methods=["GET"])
def telemetria_api():
    try:
        t0 = time.time()
        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        
        # Fecha de hoy y nombre del día
        fecha_hoy = datetime.now().strftime("%Y-%m-%d")
        dia_nombre_en = datetime.now().strftime("%A")
        dia_nombre = DIAS_ESP.get(dia_nombre_en, dia_nombre_en)

        # 1. Total OCs registradas (global y por empresa)
        cur.execute("SELECT COUNT(*) FROM ordenes_compra_ek9 WHERE empresa = 'ARYVE';")
        total_aryve = cur.fetchone()[0] or 0

        cur.execute("SELECT COUNT(*) FROM ordenes_compra_ek9 WHERE empresa = 'VELAS';")
        total_velas = cur.fetchone()[0] or 0

        total_global = total_aryve + total_velas

        # 2. OCs de HOY separadas: Aryve y Velas
        cur.execute("""
            SELECT 
                SUM(CASE WHEN empresa = 'ARYVE' THEN 1 ELSE 0 END),
                SUM(CASE WHEN empresa = 'VELAS' THEN 1 ELSE 0 END),
                ROUND(COALESCE(SUM(CASE WHEN empresa = 'ARYVE' THEN total ELSE 0 END), 0), 2),
                ROUND(COALESCE(SUM(CASE WHEN empresa = 'VELAS' THEN total ELSE 0 END), 0), 2)
            FROM ordenes_compra_ek9
            WHERE fecha = date('now', 'localtime');
        """)
        row_hoy = cur.fetchone()
        ocs_hoy_aryve = row_hoy[0] or 0
        ocs_hoy_velas = row_hoy[1] or 0
        monto_hoy_aryve = row_hoy[2] or 0.0
        monto_hoy_velas = row_hoy[3] or 0.0

        # 3. Desglose de los últimos 5 días con actividad (Aryve + Velas)
        cur.execute("""
            SELECT fecha,
                   SUM(CASE WHEN empresa = 'ARYVE' THEN 1 ELSE 0 END) as cant_aryve,
                   SUM(CASE WHEN empresa = 'VELAS' THEN 1 ELSE 0 END) as cant_velas,
                   COUNT(*) as cant_total
            FROM ordenes_compra_ek9
            GROUP BY fecha
            ORDER BY fecha DESC
            LIMIT 5;
        """)
        filas_dias = cur.fetchall()
        historial_dias = []
        for f, c_aryve, c_velas, c_tot in filas_dias:
            try:
                dt = datetime.strptime(f, "%Y-%m-%d")
                d_esp = DIAS_ESP.get(dt.strftime("%A"), dt.strftime("%A"))
            except Exception:
                d_esp = f
            historial_dias.append({
                "fecha": f,
                "dia": d_esp,
                "aryve": c_aryve,
                "velas": c_velas,
                "total": c_tot
            })

        # 4. Total de pagos programados / conciliados
        cur.execute("SELECT COUNT(*), COALESCE(SUM([PAGO A]), 0) FROM pagos;")
        row_pagos = cur.fetchone()
        pagos_prog = row_pagos[0] or 0
        monto_pagos = round(row_pagos[1] or 0.0, 2)

        conn.close()
        latencia_db_ms = round((time.time() - t0) * 1000, 2)

        metricas_cache = cache_global.obtener_metricas()
        uptime_segundos = int(time.time() - TIEMPO_ARRANQUE)

        return jsonify({
            "status": "ok",
            "estado": "OPERATIVO",
            "salud": "100%",
            "version": "1.0.0",
            "servidor": "Docker / Gunicorn Multi-Worker",
            "uptime_segundos": uptime_segundos,
            "latencia_db_ms": latencia_db_ms,
            "datos": {
                "fecha_hoy": fecha_hoy,
                "dia_hoy": dia_nombre,
                # HOY separado
                "ocs_hoy_aryve": ocs_hoy_aryve,
                "ocs_hoy_velas": ocs_hoy_velas,
                "ocs_hoy_total": ocs_hoy_aryve + ocs_hoy_velas,
                "monto_hoy_aryve": monto_hoy_aryve,
                "monto_hoy_velas": monto_hoy_velas,
                # Totales acumulados
                "total_aryve": total_aryve,
                "total_velas": total_velas,
                "total_global": total_global,
                # Historial de días
                "historial_dias": historial_dias,
                "pagos_programados": pagos_prog,
                "monto_programado": monto_pagos,
                "errores": 0
            },
            "cache": metricas_cache,
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        })
    except Exception as e:
        return jsonify({
            "status": "error",
            "estado": "DEGRADADO",
            "salud": "0%",
            "mensaje": str(e),
            "datos": {"errores": 1}
        }), 500

@metricas_bp.route("/api/limpiar-cache", methods=["POST"])
def limpiar_cache_api():
    cache_global.invalidar_todo()
    return jsonify({
        "status": "ok",
        "mensaje": "Caché Hash en RAM vaciada. La siguiente consulta medirá el tiempo de lectura física al B-Tree en disco."
    })

@metricas_bp.route("/api/reindexar", methods=["POST"])
def reindexar():
    try:
        indexador.indexar_ecosistema()
        cache_global.invalidar_todo()
        return jsonify({"status": "ok", "mensaje": "Ecosistema re-indexado exitosamente"})
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500
