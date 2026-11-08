import os
import sqlite3
import pandas as pd
import time

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATOS_DIR = os.path.join(BASE_DIR, "datos")
DB_PATH = os.path.join(DATOS_DIR, "ecosistema_oc.db")

CSV_PAGOS = os.path.join(DATOS_DIR, "pagos.csv")
CSV_PROVEEDORES = os.path.join(DATOS_DIR, "proveedores.csv")
CSV_INSUMOS = os.path.join(DATOS_DIR, "insumos.csv")

def normalizar_numero(val):
    if pd.isna(val) or val is None or val == "":
        return 0.0
    s = str(val).replace("$", "").replace(",", "").strip()
    try:
        return float(s)
    except Exception:
        return 0.0

def indexar_ecosistema():
    print("=" * 65)
    print(" [INDEXADOR] Ecosistema de Datos y Grafo Relacional (SQLite)")
    print(f" Destino: {DB_PATH}")
    print("=" * 65)
    t0 = time.time()

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    # Optimizaciones de SQLite para alto rendimiento en lecturas y escrituras
    cursor.execute("PRAGMA journal_mode = WAL;")
    cursor.execute("PRAGMA synchronous = NORMAL;")
    cursor.execute("PRAGMA cache_size = 10000;")

    # 1. Tabla Proveedores
    if os.path.exists(CSV_PROVEEDORES):
        print("\n[1/3] Indexando Proveedores...")
        df_prov = pd.read_csv(CSV_PROVEEDORES, low_memory=False)
        df_prov = df_prov.rename(columns={df_prov.columns[0]: 'nombre_prov', 'ID': 'id_prov', 'Clave': 'clave_prov'})
        df_prov = df_prov[['nombre_prov', 'id_prov', 'clave_prov']].dropna(subset=['id_prov'])
        df_prov['id_prov'] = df_prov['id_prov'].astype(str).str.strip()
        df_prov.to_sql("proveedores", conn, if_exists="replace", index=False)
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_prov_id ON proveedores(id_prov);")
        print(f" -> {len(df_prov)} proveedores indexados.")

    # 2. Tabla Insumos
    if os.path.exists(CSV_INSUMOS):
        print("\n[2/3] Indexando Insumos y Categorías...")
        df_ins = pd.read_csv(CSV_INSUMOS, low_memory=False)
        df_ins.to_sql("insumos", conn, if_exists="replace", index=False)
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_insumo ON insumos(insumo);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_insumo_ek10 ON insumos(Id_ek10);")
        print(f" -> {len(df_ins)} insumos indexados.")

    # 3. Tabla Pagos (71,500+ filas)
    if os.path.exists(CSV_PAGOS):
        print("\n[3/3] Indexando Pagos y Órdenes de Compra (71,500+ registros)...")
        # Leer en chunks para optimizar memoria RAM
        chunksize = 25000
        first_chunk = True
        total_filas = 0

        for chunk in pd.read_csv(CSV_PAGOS, chunksize=chunksize, low_memory=False):
            # Limpiar nombres de columnas con espacios (ej ' TIPO')
            chunk.columns = [c.strip() for c in chunk.columns]
            
            # Normalizar columnas numéricas
            chunk['IMPORTE'] = chunk['IMPORTE'].apply(normalizar_numero)
            chunk['PAGO A'] = chunk['PAGO A'].apply(normalizar_numero)
            chunk['DIFERENCIA'] = (chunk['IMPORTE'] - chunk['PAGO A']).round(2)
            
            # Normalizar texto para búsquedas rápidas
            chunk['O.C.'] = chunk['O.C.'].fillna('').astype(str).str.strip()
            chunk['ENVIADO POR'] = chunk['ENVIADO POR'].fillna('').astype(str).str.strip()
            chunk['CIA'] = chunk['CIA'].fillna('').astype(str).str.strip()
            chunk['RUBRO'] = chunk['RUBRO'].fillna('').astype(str).str.strip()
            chunk['SEMANA'] = chunk['SEMANA'].fillna('').astype(str).str.strip()
            chunk['OBRA'] = chunk['OBRA'].fillna('').astype(str).str.strip()
            chunk['CONTRATISTA'] = chunk['CONTRATISTA'].fillna('').astype(str).str.strip()
            chunk['ZONA'] = chunk['ZONA'].fillna('').astype(str).str.strip().replace({'Playa del Carmen': 'Playa Del Carmen'})
            chunk['FRACCIONAMIENTO'] = chunk['FRACCIONAMIENTO'].fillna('').astype(str).str.strip()
            chunk['CONCILIADO'] = chunk['ESTATUS'].astype(str).str.upper().isin(['APROBADO', 'OK', 'SI', 'PAGADO']).astype(int)

            if first_chunk:
                chunk.to_sql("pagos", conn, if_exists="replace", index=True, index_label="id_fila")
                first_chunk = False
            else:
                chunk.to_sql("pagos", conn, if_exists="append", index=True, index_label="id_fila")

            total_filas += len(chunk)
            print(f" -> {total_filas} registros procesados...")

        print("\nCreando indices B-Tree de alto rendimiento para filtros instantaneos...")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_oc ON pagos(`O.C.`);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_semana ON pagos(SEMANA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_enviado ON pagos(`ENVIADO POR`);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_rubro ON pagos(RUBRO);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_zona ON pagos(ZONA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_fracc ON pagos(FRACCIONAMIENTO);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_obra ON pagos(OBRA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prov ON pagos(`ID PROV`);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_resp_sem ON pagos(`ENVIADO POR`, SEMANA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_zona_fracc ON pagos(ZONA, FRACCIONAMIENTO);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_propietario ON pagos(PROPIETARIO);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_zona ON pagos(PROPIETARIO, ZONA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_semana ON pagos(PROPIETARIO, SEMANA);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_enviado ON pagos(PROPIETARIO, `ENVIADO POR`);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_rubro ON pagos(PROPIETARIO, RUBRO);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_zona_id ON pagos(PROPIETARIO, ZONA, id_fila DESC);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_id ON pagos(PROPIETARIO, id_fila DESC);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_sem_id ON pagos(PROPIETARIO, SEMANA, id_fila DESC);")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_pagos_prop_enviado_id ON pagos(PROPIETARIO, `ENVIADO POR`, id_fila DESC);")
        
        print("\nEjecutando ANALYZE y estadisticas de optimizador SQLite...")
        cursor.execute("ANALYZE;")
        cursor.execute("PRAGMA optimize;")

    conn.commit()
    conn.close()
    dt = round(time.time() - t0, 2)
    print(f"\n[OK] INDEXACION COMPLETADA EN {dt} SEGUNDOS!")
    print(f"Base de datos SQLite optimizada lista: {DB_PATH}")

if __name__ == "__main__":
    indexar_ecosistema()
