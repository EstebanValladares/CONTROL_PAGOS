"""
Application Factory para inicializar Flask y registrar los Blueprints modulares.
"""
import os
from flask import Flask, make_response, render_template, session
from app.config import get_config
from app.core.auth import puede_borrar_pagos_programados
import indexador

def create_app(config_class=None):
    if config_class is None:
        config_class = get_config()
        
    flask_app = Flask(
        __name__,
        static_folder=None,
        template_folder="../templates"
    )
    flask_app.config.from_object(config_class)
    
    # Delimitadores de Jinja compatibles con Vue
    flask_app.jinja_env.variable_start_string = '{[{'
    flask_app.jinja_env.variable_end_string = '}]}'

    # Verificar indexación de la base de datos local
    if not os.path.exists(config_class.DB_PATH):
        print(f" [DB] Creando e indexando base de datos inicial en: {config_class.DB_PATH}")
        indexador.indexar_ecosistema()

    # Vista principal SPA
    @flask_app.route("/")
    def index():
        response = make_response(render_template(
            "index.html",
            documentos_habilitados=flask_app.config["DOCUMENTOS_HABILITADOS"],
            puede_borrar_pagos_sesion=(
                session.get("usuario_autenticado", False)
                and puede_borrar_pagos_programados(session.get("usuario_nombre"))
            )
        ))
        response.headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0"
        response.headers["Pragma"] = "no-cache"
        response.headers["Expires"] = "0"
        return response

    # Registro de Blueprints Modulares
    from app.modules.auth.routes import auth_bp
    from app.modules.pagos.routes import pagos_bp
    from app.modules.ordenes.routes import ordenes_bp
    from app.modules.sincronizacion.routes import sync_bp
    from app.modules.metricas.routes import metricas_bp

    flask_app.register_blueprint(auth_bp)
    flask_app.register_blueprint(pagos_bp)
    flask_app.register_blueprint(ordenes_bp)
    flask_app.register_blueprint(sync_bp)
    flask_app.register_blueprint(metricas_bp)

    return flask_app

# Instancia WSGI predeterminada para que Gunicorn pueda invocar 'app:app' tanto como paquete o como archivo
app = create_app()
