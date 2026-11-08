"""
Módulo de Configuración de la Aplicación.
Carga variables de entorno y define perfiles (Development, Production, Testing).
"""
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
DATOS_DIR = BASE_DIR / "datos"
DOCUMENTOS_HABILITADOS = False

class Config:
    SECRET_KEY = os.getenv("SECRET_KEY", "dev-secret-key-velas")
    BASE_DIR = BASE_DIR
    DATOS_DIR = DATOS_DIR
    DB_PATH = DATOS_DIR / os.getenv("DB_NAME", "ecosistema_oc.db")
    ARCHIVO_EXPORT = DATOS_DIR / "control_pagos_conciliado.xlsx"
    CACHE_DEFAULT_TTL = int(os.getenv("CACHE_DEFAULT_TTL", 180))
    DOCUMENTOS_HABILITADOS = DOCUMENTOS_HABILITADOS
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
