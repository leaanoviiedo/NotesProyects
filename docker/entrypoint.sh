#!/bin/bash
set -e

echo "============================================"
echo "  NotesProyects - Iniciando contenedor"
echo "============================================"

cd /var/www

# ---------------------------------------------------------------------------
# 1. Generar APP_KEY si no está definida
# ---------------------------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "[init] Generando APP_KEY..."
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 2. Esperar a que la base de datos esté lista
# ---------------------------------------------------------------------------
echo "[init] Esperando conexión a la base de datos..."
until php artisan db:show --no-interaction > /dev/null 2>&1; do
    echo "[init] Base de datos no disponible, reintentando en 3s..."
    sleep 3
done
echo "[init] Base de datos conectada."

# ---------------------------------------------------------------------------
# 3. Ejecutar migraciones
# ---------------------------------------------------------------------------
echo "[init] Ejecutando migraciones..."
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# 4. Crear enlace simbólico de storage (idempotente)
# ---------------------------------------------------------------------------
if [ ! -L /var/www/public/storage ]; then
    echo "[init] Creando storage:link..."
    php artisan storage:link
fi

# ---------------------------------------------------------------------------
# 5. Cachear configuración, rutas y vistas para producción
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ]; then
    echo "[init] Cacheando configuración para producción..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# ---------------------------------------------------------------------------
# 6. Asegurar permisos de storage
# ---------------------------------------------------------------------------
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "[init] Iniciando servicios con supervisord..."
echo "============================================"

# Ejecutar supervisord como proceso principal del contenedor
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
