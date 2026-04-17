#!/bin/bash

echo "============================================"
echo "  NotesProyects - Iniciando contenedor"
echo "============================================"

cd /var/www

# ---------------------------------------------------------------------------
# 1. Crear directorios de logs antes de cualquier otra cosa
# ---------------------------------------------------------------------------
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/{cache,sessions,views}
mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ---------------------------------------------------------------------------
# 2. Generar APP_KEY si no está definida
# ---------------------------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "[init] Generando APP_KEY..."
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 3. Esperar a que MySQL esté listo (check TCP con nc)
# ---------------------------------------------------------------------------
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
echo "[init] Esperando a ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 30); do
    if nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; then
        echo "[init] Base de datos lista."
        break
    fi
    echo "[init] Intento $i/30 - reintentando en 3s..."
    sleep 3
done

# ---------------------------------------------------------------------------
# 4. Ejecutar migraciones
# ---------------------------------------------------------------------------
echo "[init] Ejecutando migraciones..."
php artisan migrate --force --no-interaction || echo "[warn] Migraciones fallaron, continuando..."

# ---------------------------------------------------------------------------
# 5. Crear enlace simbólico de storage (idempotente)
# ---------------------------------------------------------------------------
if [ ! -L /var/www/public/storage ]; then
    echo "[init] Creando storage:link..."
    php artisan storage:link --force || true
fi

# ---------------------------------------------------------------------------
# 6. Cachear configuración, rutas y vistas para producción
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ]; then
    echo "[init] Cacheando configuración..."
    php artisan config:cache  || true
    php artisan route:cache   || true
    php artisan view:cache    || true
    php artisan event:cache   || true
fi

echo "[init] Iniciando supervisord..."
echo "============================================"
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
