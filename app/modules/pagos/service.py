"""
Servicio para lógica de negocio de Pagos y Filtros.
"""
from typing import Dict, Any, Tuple
from app.modules.pagos.repository import PagosRepository
from app.core.cache import cache_global
from app.core.database import obtener_version_db

class PagosService:
    @staticmethod
    def obtener_filtros(empresa: str) -> Tuple[Dict[str, Any], str]:
        v = obtener_version_db()
        params = {"empresa": empresa}
        cached = cache_global.obtener("filtros", params, db_version=v)
        if cached is not None:
            return cached, "CACHE_HASH_RAM_O1"

        filtros = PagosRepository.obtener_filtros_disponibles(empresa)
        resultado = {
            "status": "ok",
            "empresa": empresa,
            **filtros
        }
        cache_global.guardar("filtros", params, resultado, ttl=300, db_version=v)
        return resultado, "BTREE_SQLITE_DISCO"

    @staticmethod
    def consultar_pagos(params: dict) -> Tuple[Dict[str, Any], str]:
        v = obtener_version_db()
        cached = cache_global.obtener("pagos", params, db_version=v)
        if cached is not None:
            return cached, "CACHE_HASH_RAM_O1"

        limite = params.get("limite", 100)
        pagina = params.get("pagina", 1)
        offset = (pagina - 1) * limite
        params_repo = {**params, "offset": offset}

        total, suma_imp, suma_pag, suma_dif, filas = PagosRepository.consultar_pagos_paginados(params_repo)
        total_paginas = (total + limite - 1) // limite if total > 0 else 1

        resultado = {
            "status": "ok",
            "pagina": pagina,
            "total_paginas": total_paginas,
            "total_filtrado": total,
            "suma_importe": suma_imp,
            "suma_pagando": suma_pag,
            "suma_diferencia": suma_dif,
            "datos": filas
        }
        cache_global.guardar("pagos", params, resultado, ttl=180, db_version=v)
        return resultado, "BTREE_SQLITE_DISCO"

    @staticmethod
    def actualizar_pago(id_fila: int, conciliado: bool) -> bool:
        exito = PagosRepository.actualizar_estatus_conciliacion(id_fila, conciliado)
        if exito:
            cache_global.invalidar_todo()
        return exito

    @staticmethod
    def programar_pago(datos: dict) -> int:
        nuevo_id = PagosRepository.crear_programacion_pago(datos)
        cache_global.invalidar_todo()
        return nuevo_id


