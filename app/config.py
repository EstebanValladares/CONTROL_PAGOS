"""
Módulo de Configuración de la Aplicación.
Carga variables de entorno y define perfiles (Development, Production, Testing).
"""
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
DATOS_DIR = BASE_DIR / "datos"
DOCUMENTOS_HABILITADOS = True

# Configuración FTP para réplica de comprobantes Enkontrol
FTP_HABILITADO = os.getenv("FTP_HABILITADO", "true").lower() in ["true", "1", "si"]
FTP_HOST = os.getenv("FTP_HOST", "10.10.202.244")
FTP_PORT = int(os.getenv("FTP_PORT", 21))
FTP_USER = os.getenv("FTP_USER", "")
FTP_PASS = os.getenv("FTP_PASS", "")
FTP_REMOTE_DIR = os.getenv("FTP_REMOTE_DIR", "/WebEk10")
FTP_TIMEOUT = int(os.getenv("FTP_TIMEOUT", 10))

# Configuración Base de Datos BI (Microsoft SQL Server)
BI_HOST = os.getenv("BI_HOST", "10.30.0.2")
BI_PORT = int(os.getenv("BI_PORT", 1433))
BI_DB = os.getenv("BI_DB", "Aryve2")
BI_USER = os.getenv("BI_USER", "sa")
BI_PASS = os.getenv("BI_PASS", "Aryve2025*")

class Config:
    SECRET_KEY = os.getenv("SECRET_KEY", "dev-secret-key-velas")
    BASE_DIR = BASE_DIR
    DATOS_DIR = DATOS_DIR
    DB_PATH = DATOS_DIR / os.getenv("DB_NAME", "ecosistema_oc.db")
    ARCHIVO_EXPORT = DATOS_DIR / "control_pagos_conciliado.xlsx"
    CACHE_DEFAULT_TTL = int(os.getenv("CACHE_DEFAULT_TTL", 180))
    DOCUMENTOS_HABILITADOS = DOCUMENTOS_HABILITADOS
    FTP_HABILITADO = FTP_HABILITADO
    FTP_HOST = FTP_HOST
    FTP_PORT = FTP_PORT
    FTP_USER = FTP_USER
    FTP_PASS = FTP_PASS
    FTP_REMOTE_DIR = FTP_REMOTE_DIR
    FTP_TIMEOUT = FTP_TIMEOUT
    BI_HOST = BI_HOST
    BI_PORT = BI_PORT
    BI_DB = BI_DB
    BI_USER = BI_USER
    BI_PASS = BI_PASS
    DEBUG = False
    TESTING = False

class DevelopmentConfig(Config):
    DEBUG = True

class ProductionConfig(Config):
    DEBUG = False

class TestingConfig(Config):
    TESTING = True
    DB_PATH = DATOS_DIR / "test_ecosistema_oc.db"

config_by_name = {
    "development": DevelopmentConfig,
    "production": ProductionConfig,
    "testing": TestingConfig
}

def get_config():
    env = os.getenv("FLASK_ENV", "development").lower()
    return config_by_name.get(env, DevelopmentConfig)
