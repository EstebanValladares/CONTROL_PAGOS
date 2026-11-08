"""
Endpoints para la consulta y actualización de Pagos y Filtros.
"""
import time
from flask import Blueprint, request, jsonify
from app.modules.pagos.service import PagosService
from app.core.cache import cache_global

pagos_bp = Blueprint("pagos", __name__)

@pagos_bp.route("/api/filtros", methods=["GET"])
def obtener_filtros():
    t0 = time.time()
    try:
        empresa = request.args.get("empresa", "").strip().upper()
        resultado, origen = PagosService.obtener_filtros(empresa)
        ms = round((time.time() - t0) * 1000, 2)
        resp = dict(resultado)
        resp["tiempo_ms"] = ms
        resp["origen"] = origen
        resp["telemetria"] = cache_global.obtener_metricas()
        return jsonify(resp)
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@pagos_bp.route("/api/pagos", methods=["GET"])
def obtener_pagos():
    t0 = time.time()
    try:
        empresa = request.args.get("empresa", "").strip().upper()
        busqueda = request.args.get("q", "").strip()
        responsable = request.args.get("responsable", "").strip()
        plaza = request.args.get("plaza", "").strip()
        fraccionamiento = request.args.get("fraccionamiento", "").strip()
        rubro = request.args.get("rubro", "").strip()
        semana = request.args.get("semana", "").strip()
        pagina = int(request.args.get("pagina", 1))
        limite = int(request.args.get("limite", 100))

        params = {
            "empresa": empresa,
            "busqueda": busqueda,
            "responsable": responsable,
            "plaza": plaza,
            "fraccionamiento": fraccionamiento,
            "rubro": rubro,
            "semana": semana,
            "pagina": pagina,
            "limite": limite
        }

        resultado, origen = PagosService.consultar_pagos(params)
        ms = round((time.time() - t0) * 1000, 2)
        resp = dict(resultado)
        resp["tiempo_ms"] = ms
        resp["origen"] = origen
        resp["telemetria"] = cache_global.obtener_metricas()
        return jsonify(resp)
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@pagos_bp.route("/api/pagos/actualizar", methods=["POST"])
def actualizar_pago():
    try:
        body = request.json or {}
        id_fila = body.get("id_fila")
        conciliado = bool(body.get("CONCILIADO"))

        if id_fila is not None:
            exito = PagosService.actualizar_pago(id_fila, conciliado)
            if exito:
                return jsonify({"status": "ok", "mensaje": "Fila actualizada en tiempo real"})
            return jsonify({"status": "error", "mensaje": "No se encontró el registro"}), 404
        return jsonify({"status": "error", "mensaje": "Falta id_fila"}), 400
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@pagos_bp.route("/api/pagos/programar", methods=["POST"])
def programar_pago():
    try:
        body = request.json or {}
        folio = str(body.get("folio", "")).strip()
        pagando = float(body.get("pagando", 0.0) or 0.0)

        if not folio:
            return jsonify({"status": "error", "mensaje": "El folio del documento es obligatorio"}), 400
        if pagando <= 0:
            return jsonify({"status": "error", "mensaje": "El monto a programar (Pagando) debe ser mayor a $0.00"}), 400

        nuevo_id = PagosService.programar_pago(body)
        return jsonify({
            "status": "ok",
            "mensaje": f"Pago de ${pagando:,.2f} programado exitosamente",
            "id_fila": nuevo_id
        })
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500


