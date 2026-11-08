"""
Módulo de Autenticación y Autorización de Usuarios.
"""
import unicodedata


_USUARIOS_AUTORIZADOS_BORRAR_PAGOS = {
    "CLAUDIA SARAI PADILLA RAMIREZ",
    "LUIS SOTERO CRUZ GOMEZ",
    "ARTURO VILLANUEVA GUERRERO",
    "PEDRO PEREZ GUZMAN",
    "PEDRO PEREZ",
    "YEUDIEL ESTEBAN RICHARD PEREZ",
    "SANTOS PEREZ TOLENTINO",
    "IRMA MIREYA MOLINA CAZARES",
    "LUIS ANGEL PERNAS SUAREZ",
}


def puede_borrar_pagos_programados(nombre=""):
    nombre_normalizado = "".join(
        caracter
        for caracter in unicodedata.normalize("NFKD", str(nombre or "").strip().upper())
        if not unicodedata.combining(caracter)
    )
    nombre_normalizado = " ".join(nombre_normalizado.split())
    return nombre_normalizado in _USUARIOS_AUTORIZADOS_BORRAR_PAGOS


def es_usuario_administrador_autorizado(nombre="", email="", emp_id=None, rol=""):
    """
    Identifica si el usuario tiene privilegios funcionales de Modo Administrador.
    Exclusivo para la cuenta ADMIN y los 8 usuarios autorizados:
      1. CLAUDIA SARAÍ PADILLA RAMÍREZ
      2. LUIS SOTERO CRUZ GOMEZ
      3. ARTURO VILLANUEVA GUERRERO
      4. PEDRO PEREZ GUZMAN (PEDRO PEREZ)
      5. YEUDIEL ESTEBAN RICHARD PEREZ
      6. SANTOS PEREZ TOLENTINO
      7. IRMA MIREYA MOLINA CAZARES
      8. LUIS ANGEL PERNAS SUAREZ
    """
    if str(rol or "").strip().upper() == "ADMIN":
        return True

    # 1. Validación por número de empleado oficial
    try:
        if emp_id is not None and int(emp_id) in [18, 120, 166, 3399, 9119]:
            return True
    except Exception:
        pass

    nom = str(nombre or "").strip().upper()
    em = str(email or "").strip().lower()

    # 2. Validación por correos institucionales de los 8 usuarios
    emails_admin_autorizados = {
        "claudia.padilla@grupovelas.com",
        "enkontrol2@grupovelas.com",
        "enkontrol3@grupovelas.com",
        "avillanueva@grupovelas.com",
        "pedro.ek@grupovelas.com",
        "",
        "santos.perez@grupovelas.com",
        "supervision.auxiliares3@grupovelas.com",
        "supervision.auxiliares@grupovelas.com"
    }
    if em in emails_admin_autorizados:
        return True

    # 3. Validación por nombres específicos
    if "PADILLA" in nom and ("CLAUDIA" in nom or "SARAI" in nom):
        return True
    if "SOTERO" in nom and "CRUZ" in nom:
        return True
    if "VILLANUEVA" in nom and "ARTURO" in nom:
        return True
    if ("PEDRO" in nom and "PEREZ" in nom) or "PEREZ GUZMAN" in nom:
        return True
    if "YEUDIEL" in nom or ("RICHARD" in nom and "PEREZ" in nom):
        return True
    if "SANTOS" in nom and ("PEREZ" in nom or "TOLENTINO" in nom):
        return True
    if "MOLINA" in nom and ("IRMA" in nom or "MIREYA" in nom):
        return True
    if "PERNAS" in nom:
        return True


    return False

def tiene_permiso_concentrado_conciliacion(nombre="", email="", puesto="", rol="", emp_id=None):
    """
    Determina si el usuario tiene privilegios para visualizar la vista de
    Concentrado y Conciliación Semanal (exclusivo para los usuarios autorizados).
    """
    return es_usuario_administrador_autorizado(nombre=nombre, email=email, emp_id=emp_id, rol=rol)
