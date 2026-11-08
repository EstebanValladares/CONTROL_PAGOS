"""
Endpoints para la sincronización con EK9/EK10.
"""
from flask import Blueprint, request, jsonify
from app.modules.sincronizacion.service import ejecutar_sincronizacion_ek9

sync_bp = Blueprint("sincronizacion", __name__)

@sync_bp.route("/api/sincronizar-vivo", methods=["POST", "GET"])
def api_sincronizar_vivo():
    try:
        dias = int(request.args.get("dias", 7))
        res = ejecutar_sincronizacion_ek9(dias_atras=dias, verbose=False)
        return jsonify(res)
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

