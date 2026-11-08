import sqlite3
import pyodbc
import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATOS_DIR = os.path.join(BASE_DIR, "datos")
DB_PATH = os.path.join(DATOS_DIR, "ecosistema_oc.db")

conn_str_01 = (
    "Driver={SQL Anywhere 11};"
    "EngineName=especial_v9;"
    "DatabaseName=EK_ADM01_11;"
    "CommLinks=tcpip(host=10.10.202.244:2544);"
    "UID=user_write;"
    "PWD=writaryb0904##;"
)

conn_str_14 = (
    "Driver={SQL Anywhere 11};"
    "EngineName=servicio_v9;"
    "DatabaseName=EK_ADM14_11;"
    "CommLinks=tcpip(host=10.10.202.244:2546);"
    "UID=user_write;"
    "PWD=writaryb0904##;"
)

def sincronizar_usuarios():
    print(f"Conectando a SQLite local: {DB_PATH}")
    sqlite_conn = sqlite3.connect(DB_PATH)
    cur_sql = sqlite_conn.cursor()

    # Obtener el mapa de responsable -> plaza predominante en pagos
    cur_sql.execute("""
        SELECT UPPER(TRIM([ENVIADO POR])), ZONA, COUNT(*)
        FROM pagos
        WHERE [ENVIADO POR] IS NOT NULL AND [ENVIADO POR] != ''
        GROUP BY UPPER(TRIM([ENVIADO POR])), ZONA
        ORDER BY COUNT(*) DESC
    """)
    mapa_zonas = {}
    for nombre_resp, zona, cnt in cur_sql.fetchall():
        if nombre_resp not in mapa_zonas:
            mapa_zonas[nombre_resp] = zona

    print(f"Mapa de zonas por responsable cargado: {len(mapa_zonas)} usuarios con zona detectada.")

    # Recrear tabla usuarios_ek9 con soporte para empresa y plaza
    cur_sql.execute("DROP TABLE IF EXISTS usuarios_ek9;")
    cur_sql.execute("""
        CREATE TABLE usuarios_ek9 (
            id_usuario_local INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado INTEGER,
            nombre TEXT,
            puesto TEXT,
            email TEXT,
            password TEXT,
            rol TEXT,
            empresa TEXT,
            plaza TEXT,
            estatus TEXT DEFAULT 'A'
        );
    """)

    query_ek010ab = """
        SELECT 
            u.num,
            u.nom,
            COALESCE(p.descripcion, ''),
            u.direccion_correo,
            CASE 
                WHEN LENGTH(u.pass) >= 16 AND LENGTH(u.pass) % 16 = 0 
                THEN cast( decrypt(u.pass, 'Enkontrol' + string(u.num) ) as char(100))
                ELSE cast( u.pass as char(100) )
            END as password_real,
            u.estatus
        FROM DBA.ek010ab u
        LEFT JOIN DBA.empleados e ON u.num = e.empleado
        LEFT JOIN DBA.si_puestos p ON e.puesto = p.puesto
        WHERE u.pass IS NOT NULL AND u.pass != '';
    """

    # 1. Sincronizar desde 01 (CONSTRUCCIONES ARYVE)
    print("\n[1/2] Sincronizando usuarios desde EK_ADM01_11 (CONSTRUCCIONES ARYVE - DBA.ek010ab)...")
    try:
        c01 = pyodbc.connect(conn_str_01, timeout=8, readonly=True)
        cur01 = c01.cursor()
        cur01.execute(query_ek010ab)
        filas_01 = cur01.fetchall()
        c01.close()

        count_01 = 0
        for emp_id, emp_nom, puesto, email, pwd, estatus in filas_01:
            nom_limpio = str(emp_nom or '').strip()
            plaza_asig = mapa_zonas.get(nom_limpio.upper(), "Tampico")
            cur_sql.execute("""
                INSERT INTO usuarios_ek9 (empleado, nombre, puesto, email, password, rol, empresa, plaza, estatus)
                VALUES (?, ?, ?, ?, ?, 'RESPONSABLE', 'ARYVE', ?, ?)
            """, (int(emp_id), nom_limpio, str(puesto or ''), str(email or '').strip() if email else None, str(pwd or '').strip(), plaza_asig, str(estatus or 'A').strip()))
            count_01 += 1
        print(f" -> {count_01} usuarios oficiales sincronizados para CONSTRUCCIONES ARYVE.")
    except Exception as e:
        print(f"ERROR sincronizando 01: {e}")

    # 2. Sincronizar desde 14 (GRUPO VELAS)
    print("\n[2/2] Sincronizando usuarios desde EK_ADM14_11 (GRUPO VELAS - DBA.ek010ab)...")
    try:
        c14 = pyodbc.connect(conn_str_14, timeout=8, readonly=True)
        cur14 = c14.cursor()
        cur14.execute(query_ek010ab)
        filas_14 = cur14.fetchall()
        c14.close()

        count_14 = 0
        for emp_id, emp_nom, puesto, email, pwd, estatus in filas_14:
            nom_limpio = str(emp_nom or '').strip()
            plaza_asig = mapa_zonas.get(nom_limpio.upper(), "Los Cabos")
            cur_sql.execute("""
                INSERT INTO usuarios_ek9 (empleado, nombre, puesto, email, password, rol, empresa, plaza, estatus)
                VALUES (?, ?, ?, ?, ?, 'RESPONSABLE', 'VELAS', ?, ?)
            """, (int(emp_id), nom_limpio, str(puesto or ''), str(email or '').strip() if email else None, str(pwd or '').strip(), plaza_asig, str(estatus or 'A').strip()))
            count_14 += 1
        print(f" -> {count_14} usuarios oficiales sincronizados para GRUPO VELAS.")
    except Exception as e:
        print(f"ERROR sincronizando 14: {e}")

    # Crear índices para login ultrarrápido O(1)
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_emp ON usuarios_ek9(empleado, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_email ON usuarios_ek9(email, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_nombre ON usuarios_ek9(nombre, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_email_nocase ON usuarios_ek9(email COLLATE NOCASE, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_nombre_nocase ON usuarios_ek9(nombre COLLATE NOCASE, empresa);")
    cur_sql.execute("CREATE INDEX IF NOT EXISTS idx_usr_estatus ON usuarios_ek9(estatus, empresa);")
    sqlite_conn.commit()
    sqlite_conn.close()
    print("\n[ÉXITO] Directorio de usuarios de ambas empresas sincronizado en SQLite local.")

if __name__ == "__main__":
    sincronizar_usuarios()

