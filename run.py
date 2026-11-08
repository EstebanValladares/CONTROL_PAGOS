"""
Punto de entrada de la aplicación CONTROL_PAGOS_OC.
Inicia el servidor web Flask y el demonio de sincronización en segundo plano.
"""
import os
import sys
from threading import Thread

# Asegurar que el directorio raíz esté en sys.path
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from app import create_app
from app.modules.sincronizacion.service import demonio_sincronizacion_segundo_plano

app = create_app()

if __name__ == "__main__":
    print("\n" + "=" * 65)
    print(" [SERVIDOR] Control y Conciliación de Pagos OC (Modular Clean Architecture)")
    print(" [CAPA 1]   In-Memory Hash Cache O(1) (< 1ms en RAM)")
    print(" [CAPA 2]   SQLite B-Tree Index O(log N) (71,500+ registros)")
    print(" [MODULOS]  auth, pagos, ordenes, sincronizacion, metricas")
    print(" [URL]      http://localhost:5000")
    print("=" * 65 + "\n")

    # Iniciar sincronizador en segundo plano (cada 2 minutos)
    if not app.debug or os.environ.get("WERKZEUG_RUN_MAIN") == "true":
        hilo_sync = Thread(target=demonio_sincronizacion_segundo_plano, args=(120,), daemon=True)
        hilo_sync.start()

    app.run(host="0.0.0.0", port=5000, debug=True)

