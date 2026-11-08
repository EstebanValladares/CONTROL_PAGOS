"""
Servicio y tareas en segundo plano para sincronización con Sybase EK9/EK10.
"""
import time
from threading import Lock

sync_lock = Lock()

def ejecutar_sincronizacion_ek9(dias_atras: int = 7, verbose: bool = False) -> dict:
    """
    Ejecuta la sincronización incremental de los últimos N días desde Sybase (EK9/EK10).
    Thread-safe con sync_lock.
    """
    if not sync_lock.acquire(blocking=False):
        return {"status": "en_progreso", "mensaje": "Ya hay una sincronización en curso"}
    try:
        import sincronizar_ordenes_ek9
        from app.core.cache import cache_global
        res = sincronizar_ordenes_ek9.sincronizar_ordenes_rapido(dias_atras=dias_atras, verbose=verbose)
        cache_global.invalidar_todo()
        return res
    except Exception as e:
        return {"status": "error", "mensaje": str(e)}
    finally:
        sync_lock.release()

def demonio_sincronizacion_segundo_plano(intervalo_segundos: int = 120):
    """
    Hilo daemon que sincroniza en segundo plano las órdenes de EK10/EK9 periódicamente.
    """
    print(f" [BACKGROUND] Demonio de auto-sincronización EK10 iniciado (intervalo: {intervalo_segundos}s)...")
    while True:
        try:
            time.sleep(intervalo_segundos)
            ejecutar_sincronizacion_ek9(dias_atras=7, verbose=False)
        except Exception as e:
            print(f" [BACKGROUND ERROR] Auto-sincronización EK10: {e}")

