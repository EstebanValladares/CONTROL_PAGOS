"""
Módulo para sincronización de comprobantes y facturas hacia el servidor FTP de Enkontrol.
Utiliza ftplib nativo de Python para máxima compatibilidad y rendimiento.
"""
import os
import ftplib
from typing import List, Dict, Any, Tuple
from app.config import get_config

config = get_config()

class FTPService:
    @staticmethod
    def _obtener_cliente_ftp() -> ftplib.FTP:
        """Inicializa y autentica la conexión FTP según la configuración activa."""
        host = getattr(config, "FTP_HOST", os.getenv("FTP_HOST", "10.10.202.244"))
        port = int(getattr(config, "FTP_PORT", os.getenv("FTP_PORT", 21)))
        user = getattr(config, "FTP_USER", os.getenv("FTP_USER", "anonymous"))
        password = getattr(config, "FTP_PASS", os.getenv("FTP_PASS", ""))
        timeout = int(getattr(config, "FTP_TIMEOUT", 10))

        ftp = ftplib.FTP(timeout=timeout)
        ftp.connect(host=host, port=port)
        ftp.login(user=user, passwd=password)
        ftp.set_pasv(True)
        return ftp

    @classmethod
    def probar_conexion(cls) -> Tuple[bool, str]:
        """Prueba las credenciales y conectividad con el servidor FTP."""
        try:
            ftp = cls._obtener_cliente_ftp()
            bienvenida = ftp.getwelcome()
            ftp.quit()
            return True, f"Conexión FTP exitosa: {bienvenida}"
        except Exception as e:
            return False, f"Error conectando al FTP: {str(e)}"

    @classmethod
    def subir_archivos(cls, rutas: List[str]) -> List[Dict[str, Any]]:
        """
        Sube una lista de rutas locales de archivos al directorio remoto del FTP.
        Retorna la lista de resultados de cada archivo.
        """
        if not getattr(config, "FTP_HABILITADO", False):
            return [{"ruta": r, "subido": False, "motivo": "FTP_HABILITADO es False"} for r in rutas]

        remote_dir = getattr(config, "FTP_REMOTE_DIR", os.getenv("FTP_REMOTE_DIR", "/WebEk10")).strip()
        resultados = []

        try:
            ftp = cls._obtener_cliente_ftp()
            
            # Navegar o crear directorio remoto si no existe
            if remote_dir and remote_dir not in ["/", "."]:
                partes = [p for p in remote_dir.replace("\\", "/").split("/") if p]
                for p in partes:
                    try:
                        ftp.cwd(p)
                    except ftplib.error_perm:
                        try:
                            ftp.mkd(p)
                            ftp.cwd(p)
                        except Exception:
                            pass

            for ruta in rutas:
                if not os.path.exists(ruta):
                    resultados.append({"ruta": ruta, "subido": False, "motivo": "Archivo local no encontrado"})
                    continue

                nombre_archivo = os.path.basename(ruta)
                try:
                    with open(ruta, "rb") as f:
                        ftp.storbinary(f"STOR {nombre_archivo}", f)
                    resultados.append({
                        "ruta": ruta,
                        "archivo": nombre_archivo,
                        "subido": True,
                        "directorio_remoto": remote_dir
                    })
                    print(f" [FTP] Archivo {nombre_archivo} subido exitosamente a {remote_dir}")
                except Exception as err:
                    resultados.append({
                        "ruta": ruta,
                        "archivo": nombre_archivo,
                        "subido": False,
                        "motivo": str(err)
                    })
                    print(f" [FTP ERROR] Falló subida de {nombre_archivo}: {err}")

            ftp.quit()
        except Exception as e:
            print(f" [FTP ERROR GENERAL] {e}")
            for ruta in rutas:
                if not any(r["ruta"] == ruta for r in resultados):
                    resultados.append({"ruta": ruta, "subido": False, "motivo": str(e)})

        return resultados
