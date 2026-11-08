FROM python:3.10-slim

WORKDIR /app

# Instalar curl para healthchecks del contenedor
RUN apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 5000

# Gunicorn en modo gthread: 4 workers x 4 threads = 16 peticiones simultaneas de alta concurrencia
CMD ["gunicorn", "--workers=4", "--threads=4", "--worker-class=gthread", "--worker-connections=1000", "--timeout=60", "--keep-alive=5", "--bind=0.0.0.0:5000", "app:app"]

