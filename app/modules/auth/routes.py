"""
Rutas de autenticación de usuarios y sesiones.
"""
from flask import Blueprint, request, jsonify, session
from app.core.database import obtener_conexion
from app.core.auth import (
    es_usuario_administrador_autorizado,
    puede_borrar_pagos_programados,
)

auth_bp = Blueprint("auth", __name__)

@auth_bp.route("/api/login", methods=["POST"])
def login_api():
    try:
        datos = request.get_json() or {}
        session.clear()
        usuario = str(datos.get("usuario", "")).strip()
        password = str(datos.get("password", "")).strip()
        empresa = str(datos.get("empresa", "ARYVE")).strip().upper()
        if empresa not in ["ARYVE", "VELAS"]:
            empresa = "ARYVE"

        if not usuario or not password:
            return jsonify({"status": "error", "mensaje": "Ingresa tu usuario y contraseña"}), 400

        # Acceso Administrador General
        if usuario.upper() == "ADMIN":
            if password in ["admin", "admin123", "velas2026", "admin2026", ""]:
                session["usuario_autenticado"] = True
                session["usuario_nombre"] = "ADMINISTRADOR"
                return jsonify({
                    "status": "ok",
                    "mensaje": "Acceso como Administrador autorizado",
                    "usuario": {
                        "id": 0,
                        "nombre": "ADMINISTRADOR",
                        "puesto": "SUPERVISIÓN GENERAL",
                        "email": "admin@grupovelas.com",
                        "rol": "ADMIN",
                        "empresa": empresa,
                        "plaza": "TODAS",
                        "puede_ver_conciliacion": True,
                        "es_admin_funcional": True,
                        "puede_borrar_pagos": False,
                        "permiso_borrar_pagos_version": 2
                    }
                })
            else:
                return jsonify({"status": "error", "mensaje": "Contraseña de administrador incorrecta"}), 401

        # Validación contra directorio de empleados de ambas empresas
        conn = obtener_conexion(read_only=True)
        cur = conn.cursor()
        if usuario.isdigit():
            cur.execute("""
                SELECT empleado, nombre, puesto, email, password, rol, empresa, plaza, estatus
                FROM usuarios_ek9
                WHERE empleado = ? AND empresa = ?
            """, (int(usuario), empresa))
        else:
            cur.execute("""
                SELECT empleado, nombre, puesto, email, password, rol, empresa, plaza, estatus
                FROM usuarios_ek9
                WHERE (email = ? COLLATE NOCASE OR nombre = ? COLLATE NOCASE)
                  AND empresa = ?
            """, (usuario, usuario, empresa))
        fila = cur.fetchone()
        conn.close()

        if not fila:
            nombre_grupo_txt = "Grupo Velas" if empresa == "VELAS" else "Construcciones Aryve"
            return jsonify({
                "status": "error",
                "mensaje": f"El usuario no se encuentra disponible en {nombre_grupo_txt}. Verifica la empresa seleccionada o tu número de empleado."
            }), 401

        emp_id, emp_nombre, emp_puesto, emp_email, emp_pass, emp_rol, emp_empresa, emp_plaza, emp_estatus = fila

        # Validación de estatus en EnKontrol: 'A' = Activo, 'B' = Baja / Inactivo
        if str(emp_estatus or "A").strip().upper() == "B":
            return jsonify({
                "status": "error",
                "mensaje": "Acceso restringido: Tu usuario se encuentra inactivo (Estatus B) en el sistema EnKontrol. Contacta a Sistemas o Administración."
            }), 403

        if str(emp_pass or "").strip() != password:
            return jsonify({
                "status": "error",
                "mensaje": "Contraseña incorrecta. El acceso no se encuentra disponible con estas credenciales."
            }), 401

        es_admin_func = es_usuario_administrador_autorizado(
            nombre=emp_nombre,
            email=emp_email,
            emp_id=emp_id,
            rol=emp_rol
        )
        puede_borrar = puede_borrar_pagos_programados(nombre=emp_nombre)
        session["usuario_autenticado"] = True
        session["usuario_nombre"] = emp_nombre

        return jsonify({
            "status": "ok",
            "mensaje": f"Bienvenido(a), {emp_nombre}",
            "usuario": {
                "id": emp_id,
                "nombre": emp_nombre,
                "puesto": emp_puesto,
                "email": emp_email,
                "rol": emp_rol,
                "empresa": emp_empresa,
                "plaza": emp_plaza or ("Los Cabos" if empresa == "VELAS" else "Tampico"),
                "puede_ver_conciliacion": es_admin_func,
                "es_admin_funcional": es_admin_func,
                "puede_borrar_pagos": puede_borrar,
                "permiso_borrar_pagos_version": 2
            }
        })
    except Exception as e:
        return jsonify({"status": "error", "mensaje": str(e)}), 500


@auth_bp.route("/api/logout", methods=["POST"])
def logout_api():
    session.clear()
    return jsonify({"status": "ok"})
