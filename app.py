"""Compatibility launcher for the modular Flask application."""
from app import app


if __name__ == "__main__":
    print(" [SERVIDOR] Control y Conciliación de Pagos OC")
    print(" [URL]      http://localhost:5000")
    app.run(host="0.0.0.0", port=5000, debug=app.debug)
