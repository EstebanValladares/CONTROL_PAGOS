"""
Endpoints para la consulta de Órdenes de Compra y descarga de reportes.
"""
from flask import Blueprint, current_app, request, jsonify, send_file, session
from app.modules.ordenes.service import OrdenesService
from app.modules.ordenes.repository import OrdenesRepository
from app.modules.sincronizacion.service import ejecutar_sincronizacion_ek9

ordenes_bp = Blueprint("ordenes", __name__)

@ordenes_bp.route("/api/ordenes-recientes", methods=["GET"])
def obtener_ordenes_recientes():
    try:
        if request.args.get("sync", "").lower() in ["1", "true", "si"]:
            ejecutar_sincronizacion_ek9(dias_atras=7, verbose=False)

        empresa = request.args.get("empresa", "ARYVE").strip().upper()
        if empresa not in ["ARYVE", "VELAS"]:
            empresa = "ARYVE"

        empleado_id = request.args.get("empleado_id", "").strip()
        filtro_temporal = request.args.get("temporal", "HOY").strip().upper()
        filtro_semana = request.args.get("semana", "").strip()
        busqueda = request.args.get("busqueda", request.args.get("q", "")).strip()
        obra = request.args.get("obra", "").strip()
        mis_ordenes_raw = str(request.args.get("mis_ordenes", "1")).strip().lower()
        mis_ordenes = mis_ordenes_raw in ["1", "true", "yes"]
        pagina = int(request.args.get("pagina", 1))
        limite = int(request.args.get("limite", 50))

        params = {
            "empresa": empresa,
            "empleado_id": empleado_id,
            "filtro_temporal": filtro_temporal,
            "semana": filtro_semana,
            "busqueda": busqueda,
            "obra": obra,
            "mis_ordenes": mis_ordenes,
            "pagina": pagina,
            "limite": limite
        }

        res = OrdenesService.obtener_ordenes_recientes(params)
        return jsonify(res)
    except Exception as e:
        import traceback
        tb = traceback.format_exc()
        print("[API ERROR]", tb)
        return jsonify({"status": "error", "mensaje": str(e), "traceback": tb}), 500

@ordenes_bp.route("/api/ordenes/catalogo-obras", methods=["GET"])
def obtener_catalogo_obras():
    try:
        empresa = request.args.get("empresa", "ARYVE").strip().upper()
        obras = OrdenesRepository.obtener_catalogo_obras(empresa)
        return jsonify({"status": "ok", "obras": obras})
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/descargar-excel", methods=["GET"])
def descargar_excel():
    try:
        empresa = request.args.get("empresa", "").strip().upper()
        plaza = request.args.get("plaza", "").strip()
        semana = request.args.get("semana", "").strip()
        responsable = request.args.get("responsable", "").strip()

        params = {
            "empresa": empresa,
            "plaza": plaza,
            "semana": semana,
            "responsable": responsable
        }

        archivo_path = OrdenesService.generar_excel_conciliado(params)
        return send_file(archivo_path, as_attachment=True, download_name="concentrado_oc_conciliado.xlsx")
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500



import os
import re
import time
import uuid
from decimal import Decimal, InvalidOperation
from xml.etree import ElementTree
from werkzeug.utils import secure_filename
from app.config import BASE_DIR, DOCUMENTOS_HABILITADOS
from app.core.auth import puede_borrar_pagos_programados

# ==============================================================================
# CONFIGURACIÓN DE ALMACENAMIENTO DE DOCUMENTOS
# ==============================================================================
ENKONTROL_WEB_DIR = r"C:\EnkontrolV9\WebEk10"
if DOCUMENTOS_HABILITADOS:
    try:
        os.makedirs(ENKONTROL_WEB_DIR, exist_ok=True)
    except Exception:
        pass

DOCS_DIR = os.path.join(str(BASE_DIR), "documentos_ejemplo")
if DOCUMENTOS_HABILITADOS:
    os.makedirs(DOCS_DIR, exist_ok=True)

def _guardar_documentos_oc(
    cur, archivos, empresa, centro_costos, numero_oc, id_abono,
    creado_por, creado_en, archivos_creados, persistir_db=True
):
    guardados = []
    os.makedirs(DOCS_DIR, exist_ok=True)

    for archivo in archivos:
        if not archivo or not archivo.filename:
            continue

        nombre_base = secure_filename(archivo.filename) or "documento.dat"
        nombre_final = (
            f"{secure_filename(centro_costos) or 'obra'}_OC{numero_oc}_"
            f"{uuid.uuid4().hex}_{nombre_base}"
        )
        ruta_completa = os.path.join(DOCS_DIR, nombre_final)
        archivo.save(ruta_completa)
        archivos_creados.append(ruta_completa)
        tamano = os.path.getsize(ruta_completa)

        if persistir_db:
            cur.execute("""
                INSERT INTO documentos_oc (
                    empresa, centro_costos, numero_oc, id_abono,
                    nombre_original, nombre_guardado, ruta_archivo,
                    tamano_bytes, creado_por, creado_en
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                empresa, centro_costos, numero_oc, id_abono,
                archivo.filename, nombre_final, ruta_completa,
                tamano, creado_por, creado_en
            ))
        guardados.append({
            "nombre_original": archivo.filename,
            "nombre_guardado": nombre_final,
            "tamano": tamano
        })

    return guardados

def _limpiar_archivos_creados(rutas):
    for ruta in rutas:
        try:
            if os.path.exists(ruta):
                os.remove(ruta)
        except OSError as error:
            print(f"[DOCS] No se pudo limpiar el archivo {ruta}: {error}")

def _extraer_total_factura_xml(archivo):
    try:
        contenido = archivo.read()
        raiz = ElementTree.fromstring(contenido)
    except ElementTree.ParseError as error:
        raise ValueError(f"El XML de factura '{archivo.filename}' no es válido") from error
    finally:
        archivo.stream.seek(0)

    if raiz.tag.rsplit("}", 1)[-1] != "Comprobante":
        raise ValueError(f"El XML '{archivo.filename}' no es un CFDI Comprobante")

    total_raw = raiz.attrib.get("Total")
    if not total_raw or not re.fullmatch(r"\d{1,18}(?:\.\d{1,6})?", total_raw):
        raise ValueError(f"El XML '{archivo.filename}' no contiene el total de la factura")
    try:
        total = Decimal(total_raw)
    except InvalidOperation as error:
        raise ValueError(f"El XML '{archivo.filename}' contiene un total inválido") from error
    if not total.is_finite() or total < 0:
        raise ValueError(f"El XML '{archivo.filename}' contiene un total inválido")
    return total

def _validar_total_xml_menor_pago(total_xml, monto_pago, numero_oc):
    if total_xml is not None and total_xml >= Decimal(str(monto_pago)):
        raise ValueError(
            f"La suma de las facturas XML de la OC #{numero_oc} "
            f"(${total_xml:,.2f}) debe ser menor al importe del pago "
            f"(${Decimal(str(monto_pago)):,.2f})"
        )

def _replicar_documentos_enkontrol(rutas):
    import shutil

    for ruta in rutas:
        try:
            if os.path.isdir(ENKONTROL_WEB_DIR):
                shutil.copy2(ruta, os.path.join(ENKONTROL_WEB_DIR, os.path.basename(ruta)))
        except OSError as error:
            print(f"[DOCS] Error replicando a WebEk10: {error}")

@ordenes_bp.route("/api/subir-documentos", methods=["POST"])
def subir_documentos():
    archivos_creados = []
    try:
        if not current_app.config["DOCUMENTOS_HABILITADOS"]:
            return jsonify({
                "status": "error",
                "mensaje": "La carga de documentos está desactivada durante las pruebas."
            }), 409

        from app.core.database import transaccion
        
        archivos = request.files.getlist("archivos")
        if not archivos:
            archivos = [f for k, f in request.files.items() if f.filename]

        if not archivos:
            return jsonify({"status": "error", "mensaje": "No se seleccionaron archivos"}), 400

        empresa = request.form.get("empresa", "ARYVE").strip().upper()
        centro_costos = request.form.get("centro_costos", "").strip()
        numero_oc_raw = request.form.get("numero_oc", "0")
        numero_oc = int(numero_oc_raw) if str(numero_oc_raw).isdigit() else 0
        creado_por = request.form.get("creado_por", "USUARIO").strip()

        # REGLA DE NEGOCIO: Validar que la OC no tenga ya documentos para evitar duplicados
        with transaccion() as conn:
            cur = conn.cursor()
            cur.execute("""
                SELECT count(*) FROM documentos_oc
                WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
            """, (empresa, centro_costos, numero_oc))
            total_existentes = cur.fetchone()[0]

            if total_existentes > 0:
                return jsonify({
                    "status": "error",
                    "mensaje": f"La OC #{numero_oc} de {centro_costos} ya cuenta con {total_existentes} documento(s) adjunto(s). No se permite duplicar."
                }), 400

            ahora_str = time.strftime("%Y-%m-%d %H:%M:%S")
            guardados = _guardar_documentos_oc(
                cur, archivos, empresa, centro_costos, numero_oc,
                None, creado_por, ahora_str, archivos_creados,
                persistir_db=bool(centro_costos) and numero_oc > 0
            )

        _replicar_documentos_enkontrol(archivos_creados)
        return jsonify({
            "status": "ok",
            "mensaje": f"{len(guardados)} documento(s) adjuntado(s) exitosamente",
            "guardados": guardados,
            "total_documentos": len(guardados)
        })
    except Exception as e:
        _limpiar_archivos_creados(archivos_creados)
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/documentos-oc", methods=["GET"])
def obtener_documentos_oc():
    try:
        from app.core.database import obtener_conexion
        empresa = request.args.get("empresa", "ARYVE").strip().upper()
        centro_costos = request.args.get("centro_costos", "").strip()
        numero_oc = int(request.args.get("numero_oc", 0))

        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        cur.execute("""
            SELECT id, nombre_original, nombre_guardado, tamano_bytes, creado_por, creado_en
            FROM documentos_oc
            WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
            ORDER BY id ASC;
        """, (empresa, centro_costos, numero_oc))
        docs = [dict(r) for r in cur.fetchall()]
        conn.close()
        return jsonify({"status": "ok", "documentos": docs})
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/descargar-archivo-oc/<int:doc_id>", methods=["GET"])
def descargar_archivo_oc(doc_id):
    try:
        from app.core.database import obtener_conexion
        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        cur.execute("SELECT nombre_original, nombre_guardado, ruta_archivo FROM documentos_oc WHERE id = ?", (doc_id,))
        row = cur.fetchone()
        conn.close()
        if not row:
            return jsonify({"status": "error", "mensaje": "Documento no encontrado"}), 404
        
        ruta = row["ruta_archivo"]
        if not os.path.exists(ruta):
            # Fallback a DOCS_DIR o WebEk10
            ruta_local = os.path.join(DOCS_DIR, row["nombre_guardado"])
            ruta_ek10 = os.path.join(ENKONTROL_WEB_DIR, row["nombre_guardado"])
            if os.path.exists(ruta_local):
                ruta = ruta_local
            elif os.path.exists(ruta_ek10):
                ruta = ruta_ek10
        
        return send_file(ruta, as_attachment=True, download_name=row["nombre_original"])
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/ordenes/programar-abono", methods=["POST"])
def programar_abono_oc():
    try:
        from app.core.database import transaccion
        import time

        body = request.get_json() or {}
        empresa = str(body.get("empresa", "ARYVE")).strip().upper()
        centro_costos = str(body.get("centro_costos", "")).strip()
        numero_oc = int(body.get("numero_oc", 0))
        id_ek10 = str(body.get("id_ek10", "")).strip()
        proveedor = str(body.get("proveedor", "")).strip()
        total_oc = float(body.get("total_oc", 0.0) or 0.0)
        monto_programado = float(body.get("monto_programado", 0.0) or 0.0)
        semana = str(body.get("semana", "")).strip()
        rubro = str(body.get("rubro", "MATERIALES")).strip()
        concepto = str(body.get("concepto", "")).strip()
        creado_por = str(body.get("creado_por", "USUARIO")).strip()

        if numero_oc <= 0:
            return jsonify({"status": "error", "mensaje": "Número de OC inválido"}), 400
        if monto_programado <= 0:
            return jsonify({"status": "error", "mensaje": "El monto a programar debe ser mayor a $0.00"}), 400

        ahora_str = time.strftime("%Y-%m-%d %H:%M:%S")

        with transaccion() as conn:
            cur = conn.cursor()
            cur.execute("""
                INSERT INTO programaciones_oc (
                    empresa, centro_costos, numero_oc, id_ek10, proveedor,
                    total_oc, monto_programado, semana, rubro, concepto,
                    creado_por, creado_en
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
            """, (
                empresa, centro_costos, numero_oc, id_ek10, proveedor,
                total_oc, monto_programado, semana, rubro, concepto,
                creado_por, ahora_str
            ))
            nuevo_id = cur.lastrowid

            # Consultar el acumulado actualizado
            cur.execute("""
                SELECT id, monto_programado, semana, rubro, concepto, creado_por, creado_en
                FROM programaciones_oc
                WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                ORDER BY id ASC;
            """, (empresa, centro_costos, numero_oc))
            progs = [dict(row) for row in cur.fetchall()]
            pagado_acum = sum(p["monto_programado"] for p in progs)
            saldo_restante = max(0.0, round(total_oc - pagado_acum, 2))

        return jsonify({
            "status": "ok",
            "mensaje": f"Pago de ${monto_programado:,.2f} registrado con éxito",
            "id": nuevo_id,
            "total_programado": round(pagado_acum, 2),
            "saldo_disponible": saldo_restante,
            "programaciones": progs
        })
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/ordenes/eliminar-abono", methods=["POST"])
def eliminar_abono_oc():
    try:
        if not session.get("usuario_autenticado"):
            return jsonify({"status": "error", "mensaje": "No hay sesión de usuario activa"}), 401
        if not puede_borrar_pagos_programados(session.get("usuario_nombre")):
            return jsonify({"status": "error", "mensaje": "No tienes permiso para borrar pagos programados"}), 403

        from app.core.database import transaccion

        body = request.get_json() or {}
        id_abono = int(body.get("id", 0))
        empresa = str(body.get("empresa", "ARYVE")).strip().upper()
        centro_costos = str(body.get("centro_costos", "")).strip()
        numero_oc = int(body.get("numero_oc", 0))
        total_oc = float(body.get("total_oc", 0.0) or 0.0)

        if id_abono <= 0:
            return jsonify({"status": "error", "mensaje": "ID de abono no válido"}), 400

        with transaccion() as conn:
            cur = conn.cursor()
            cur.execute("DELETE FROM programaciones_oc WHERE id = ?;", (id_abono,))

            cur.execute("""
                SELECT id, monto_programado, semana, rubro, concepto, creado_por, creado_en
                FROM programaciones_oc
                WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                ORDER BY id ASC;
            """, (empresa, centro_costos, numero_oc))
            progs = [dict(row) for row in cur.fetchall()]
            pagado_acum = sum(p["monto_programado"] for p in progs)
            saldo_restante = max(0.0, round(total_oc - pagado_acum, 2))

        return jsonify({
            "status": "ok",
            "mensaje": "Abono eliminado correctamente",
            "total_programado": round(pagado_acum, 2),
            "saldo_disponible": saldo_restante,
            "programaciones": progs
        })
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500


@ordenes_bp.route("/api/ordenes/programaciones-semana", methods=["GET"])
def obtener_programaciones_semana():
    try:
        from app.core.database import obtener_conexion
        
        empresa = request.args.get("empresa", "ARYVE").strip().upper()
        semana = request.args.get("semana", "").strip()
        obra = request.args.get("obra", "").strip()

        condiciones = ["empresa = ?"]
        valores = [empresa]

        if semana:
            semana_limpia = semana.replace("SEM:", "").replace("SEM.", "").strip()
            condiciones.append("(semana = ? OR semana = ? OR semana LIKE ?)")
            valores.extend([semana, f"SEM: {semana_limpia}", f"%{semana_limpia}%"])
        if obra:
            condiciones.append("centro_costos = ?")
            valores.append(obra)

        where_sql = "WHERE " + " AND ".join(condiciones)

        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        
        # Total acumulado y conteo
        cur.execute(f"SELECT COUNT(*), SUM(monto_programado) FROM programaciones_oc {where_sql};", valores)
        row_tot = cur.fetchone()
        total_items = row_tot[0] or 0
        total_monto = round(row_tot[1] or 0.0, 2)

        # Registros ordenados por fecha de creación desc con conteo de documentos adjuntos
        cur.execute(f"""
            SELECT id, empresa, centro_costos, numero_oc, id_ek10, proveedor,
                   total_oc, monto_programado, semana, rubro, tipo, concepto,
                   creado_por, creado_en,
                   (
                       SELECT COUNT(*)
                       FROM documentos_oc d
                       WHERE d.empresa = programaciones_oc.empresa
                         AND d.centro_costos = programaciones_oc.centro_costos
                         AND d.numero_oc = programaciones_oc.numero_oc
                   ) as total_documentos
            FROM programaciones_oc
            {where_sql}
            ORDER BY id DESC;
        """, valores)
        filas = [dict(r) for r in cur.fetchall()]
        conn.close()

        return jsonify({
            "status": "ok",
            "semana": semana,
            "obra": obra,
            "total_items": total_items,
            "total_monto": total_monto,
            "datos": filas
        })
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500

@ordenes_bp.route("/api/ordenes/programar-lote", methods=["POST"])
def programar_lote_oc():
    archivos_creados = []
    try:
        from app.core.database import transaccion
        import json
        import time

        if request.is_json:
            body = request.get_json() or {}
        else:
            body = {"items": json.loads(request.form.get("items", "[]"))}

        items = body.get("items", [])
        if not isinstance(items, list) or not items:
            return jsonify({"status": "error", "mensaje": "No se enviaron órdenes para programar"}), 400
        if any(not isinstance(item, dict) for item in items):
            return jsonify({"status": "error", "mensaje": "Uno o más registros del lote no son válidos"}), 400

        archivos_por_item = [[] for _ in items]
        xmls_por_item = [[] for _ in items]
        if current_app.config["DOCUMENTOS_HABILITADOS"]:
            for indice in range(len(items)):
                archivos = [f for f in request.files.getlist(f"archivos_{indice}") if f and f.filename]
                if archivos and not 2 <= len(archivos) <= 4:
                    return jsonify({
                        "status": "error",
                        "mensaje": f"Adjunta entre 2 y 4 documentos para la orden #{items[indice].get('numero_oc', '')}"
                    }), 400
                archivos_por_item[indice] = archivos
                xmls_por_item[indice] = [
                    archivo for archivo in archivos
                    if archivo.filename.lower().endswith(".xml")
                ]

        ahora_str = time.strftime("%Y-%m-%d %H:%M:%S")
        guardados = 0
        total_monto = 0.0
        total_documentos = 0
        items_guardados = []
        programaciones_guardadas = []

        with transaccion() as conn:
            cur = conn.cursor()
            for indice, it in enumerate(items):
                archivos = archivos_por_item[indice]
                monto = float(it.get("monto_programado", 0.0) or 0.0)
                if monto <= 0:
                    if archivos:
                        raise ValueError("No se pueden guardar documentos sin un monto programado")
                    continue

                empresa = str(it.get("empresa", "ARYVE")).strip().upper()
                centro_costos = str(it.get("centro_costos", "")).strip()
                numero_oc = int(it.get("numero_oc", 0))
                id_ek10 = str(it.get("id_ek10", "")).strip()
                proveedor = str(it.get("proveedor", "")).strip()
                total_oc = float(it.get("total_oc", 0.0) or 0.0)
                semana = str(it.get("semana", "")).strip()
                rubro = str(it.get("rubro", "MATERIALES")).strip()
                concepto = str(it.get("concepto", "")).strip()
                creado_por = str(it.get("creado_por", "USUARIO")).strip()

                # Validacion estricta: saldo disponible no puede sobrepasar el 100%
                cur.execute("""
                    SELECT COALESCE(SUM(monto_programado), 0.0)
                    FROM programaciones_oc
                    WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                """, (empresa, centro_costos, numero_oc))
                ya_prog = float(cur.fetchone()[0] or 0.0)
                saldo_disp = max(0.0, total_oc - ya_prog)

                if saldo_disp <= 0.009:
                    if archivos:
                        raise ValueError(f"La OC #{numero_oc} ya no tiene saldo disponible para guardar el pago y sus documentos")
                    continue  # Ya liquidada en cero, se omite para evitar sobrepago

                if archivos:
                    cur.execute("""
                        SELECT COUNT(*) FROM documentos_oc
                        WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?
                    """, (empresa, centro_costos, numero_oc))
                    if cur.fetchone()[0] > 0:
                        raise ValueError(f"La OC #{numero_oc} ya tiene documentos adjuntos")

                monto = min(monto, saldo_disp)
                total_xml = None
                if current_app.config["DOCUMENTOS_HABILITADOS"]:
                    total_xml = sum(
                        (_extraer_total_factura_xml(archivo) for archivo in xmls_por_item[indice]),
                        Decimal("0")
                    ) if xmls_por_item[indice] else None
                elif it.get("total_facturas_xml") is not None:
                    try:
                        total_xml = Decimal(str(it["total_facturas_xml"]))
                    except InvalidOperation as error:
                        raise ValueError(
                            f"El total XML de la OC #{numero_oc} no es válido"
                        ) from error
                    if not total_xml.is_finite() or total_xml < 0:
                        raise ValueError(
                            f"El total XML de la OC #{numero_oc} no es válido"
                        )
                _validar_total_xml_menor_pago(total_xml, monto, numero_oc)

                tipo = str(it.get("tipo", "")).strip()

                cur.execute("""
                    INSERT INTO programaciones_oc (
                        empresa, centro_costos, numero_oc, id_ek10, proveedor,
                        total_oc, monto_programado, semana, rubro, tipo, concepto,
                        creado_por, creado_en
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                """, (
                    empresa, centro_costos, numero_oc, id_ek10, proveedor,
                    total_oc, monto, semana, rubro, tipo, concepto,
                    creado_por, ahora_str
                ))
                id_abono = cur.lastrowid
                if archivos:
                    documentos = _guardar_documentos_oc(
                        cur, archivos, empresa, centro_costos, numero_oc,
                        id_abono, creado_por, ahora_str, archivos_creados
                    )
                    total_documentos += len(documentos)

                guardados += 1
                total_monto += monto
                items_guardados.append(indice)
                programaciones_guardadas.append({
                    "indice": indice,
                    "id": id_abono
                })

        _replicar_documentos_enkontrol(archivos_creados)
        return jsonify({
            "status": "ok",
            "mensaje": f"Se programaron {guardados} órdenes exitosamente por un total de ${total_monto:,.2f}",
            "guardados": guardados,
            "total_monto": round(total_monto, 2),
            "documentos_guardados": total_documentos,
            "items_guardados": items_guardados,
            "programaciones_guardadas": programaciones_guardadas
        })
    except ValueError as e:
        _limpiar_archivos_creados(archivos_creados)
        return jsonify({"status": "error", "mensaje": str(e)}), 400
    except Exception as e:
        _limpiar_archivos_creados(archivos_creados)
        return jsonify({"status": "error", "mensaje": str(e)}), 500
