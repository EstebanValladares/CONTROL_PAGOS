"""
Gestión de conexión a SQLite con optimizaciones WAL y transacciones seguras.
"""
import sqlite3
from typing import Generator
from contextlib import contextmanager
from app.config import get_config

config = get_config()

def obtener_conexion(read_only: bool = False) -> sqlite3.Connection:
    """
    Retorna una conexión configurada con soporte para nombres de columna y pragmas de alto rendimiento.
    """
    uri = f"file:{config.DB_PATH}?mode=ro" if read_only else str(config.DB_PATH)
    conn = sqlite3.connect(uri, uri=read_only, check_same_thread=False, timeout=30.0)
    conn.row_factory = sqlite3.Row
    
    # Optimizaciones SQLite
    if not read_only:
        cursor = conn.cursor()
        cursor.execute("PRAGMA journal_mode=WAL;")
        cursor.execute("PRAGMA synchronous=NORMAL;")
        cursor.execute("PRAGMA cache_size=-64000;")
        cursor.close()
    return conn

@contextmanager
def transaccion() -> Generator[sqlite3.Connection, None, None]:
    """Context manager para operaciones transaccionales seguras."""
    conn = obtener_conexion(read_only=False)
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

def obtener_version_db() -> str:
    """Devuelve la versión del esquema o timestamp de última modificación para invalidación de caché."""
    try:
        with obtener_conexion(read_only=True) as conn:
            cur = conn.cursor()
            cur.execute("PRAGMA data_version;")
            row = cur.fetchone()
            return str(row[0]) if row else "1"
    except Exception:
        return "1"

def asegurar_estado_envio_programaciones() -> None:
    """Agrega el estado de sincronización con BI a las programaciones existentes."""
    with transaccion() as conn:
        cur = conn.cursor()
        cur.execute("BEGIN IMMEDIATE;")
        cur.execute("PRAGMA table_info(programaciones_oc);")
        columnas = {fila[1] for fila in cur.fetchall()}
        if not columnas:
            raise RuntimeError("No existe la tabla programaciones_oc")
        if "estado_envio" not in columnas:
            cur.execute("""
                ALTER TABLE programaciones_oc
                ADD COLUMN estado_envio TEXT NOT NULL DEFAULT 'ENVIADO';
            """)
