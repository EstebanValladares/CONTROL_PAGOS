"""
Motor de Caché en memoria thread-safe con TTL, métricas y coherencia multi-worker.
"""
import time
from threading import Lock

class MotorHashCache:
    def __init__(self, ttl_default: int = 180):
        self._almacen = {}
        self._lock = Lock()
        self.ttl_default = ttl_default
        self.stats = {
            "total_consultas": 0,
            "hits_ram": 0,
            "misses_btree": 0,
            "ultimo_tiempo_ms": 0.0,
            "origen_ultimo": "INICIO"
        }

    def _generar_hash(self, prefijo: str, params: dict) -> str:
        tupla_clave = tuple(sorted(f"{k}:{v}" for k, v in params.items()))
        return f"{prefijo}::{hash(tupla_clave)}"

    def obtener(self, prefijo: str, params: dict, db_version: str = None):
        clave = self._generar_hash(prefijo, params)
        ahora = time.time()
        with self._lock:
            self.stats["total_consultas"] += 1
            if clave in self._almacen:
                entrada = self._almacen[clave]
                # Coherencia de caché multi-worker y validación TTL
                version_valida = (db_version is None or entrada.get("db_version") == db_version)
                if (ahora - entrada["creado_en"] <= entrada["ttl"]) and version_valida:
                    self.stats["hits_ram"] += 1
                    self.stats["origen_ultimo"] = "CACHE_HASH_RAM_O1"
                    return entrada["datos"]
                else:
                    del self._almacen[clave]
            
            self.stats["misses_btree"] += 1
            self.stats["origen_ultimo"] = "BTREE_SQLITE_DISCO"
            return None

    def guardar(self, prefijo: str, params: dict, datos, ttl: int = None, db_version: str = None):
        clave = self._generar_hash(prefijo, params)
        ttl_final = ttl or self.ttl_default
        with self._lock:
            if len(self._almacen) > 500:
                claves_viejas = sorted(self._almacen.keys(), key=lambda k: self._almacen[k]["creado_en"])[:100]
                for cv in claves_viejas:
                    del self._almacen[cv]
            
            self._almacen[clave] = {
                "datos": datos,
                "creado_en": time.time(),
                "ttl": ttl_final,
                "db_version": db_version
            }

    def invalidar_todo(self):
        with self._lock:
            self._almacen.clear()

    def obtener_metricas(self) -> dict:
        with self._lock:
            hits = self.stats["hits_ram"]
            total = self.stats["total_consultas"]
            ratio = (hits / total * 100) if total > 0 else 0.0
            return {
                **self.stats,
                "tamanio_cache": len(self._almacen),
                "hit_ratio_porcentaje": round(ratio, 2)
            }

# Instancia singleton para la aplicación
cache_global = MotorHashCache()

