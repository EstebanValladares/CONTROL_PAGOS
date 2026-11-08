# Guía de Despliegue y Configuración de Entorno

## 1. Requisitos Previos
- Python 3.10 o superior (recomendado 3.12).
- Driver ODBC de **SQL Anywhere 11** instalado en el host o contenedor.
- Acceso de red a la IP del servidor ERP: `10.10.202.244`.

## 2. Configuración Inicial

1. **Clonar repositorio y crear entorno virtual:**
   ```bash
   python -m venv venv
   source venv/bin/activate  # En Linux / Mac
   .\venv\Scripts\activate   # En Windows
   ```

2. **Instalar dependencias:**
   ```bash
   pip install -r requirements.txt
   ```

3. **Variables de entorno:**
   Copiar `.env.example` a `.env` y configurar las credenciales y puertos de conexión.
   ```bash
   cp .env.example .env
   ```

## 3. Ejecución

- **Modo Desarrollo:**
  ```bash
  python run.py
  ```
- **Modo Docker / Contenedor:**
  ```bash
  docker-compose up -d --build
  ```

