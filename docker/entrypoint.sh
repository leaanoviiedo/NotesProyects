#!/bin/bash

echo "============================================"
echo "  NotesProyects - Iniciando contenedor"
echo "============================================"

cd /var/www

# ---------------------------------------------------------------------------
# 1. Crear directorios necesarios
# ---------------------------------------------------------------------------
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/{cache,sessions,views}
mkdir -p /var/www/bootstrap/cache

# ---------------------------------------------------------------------------
# 2. Actualizar .env con las variables de entorno del contenedor
#    Usa | como delimitador para evitar conflictos con / en los valores
# ---------------------------------------------------------------------------
update_env() {
    local key="$1"
    local value="$2"
    if [ -n "$value" ]; then
        if grep -q "^${key}=" /var/www/.env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" /var/www/.env
        else
            echo "${key}=${value}" >> /var/www/.env
        fi
    fi
}

update_env "APP_KEY"            "$APP_KEY"
update_env "APP_ENV"            "$APP_ENV"
update_env "APP_URL"            "$APP_URL"
update_env "APP_DEBUG"          "$APP_DEBUG"
update_env "DB_CONNECTION"      "$DB_CONNECTION"
update_env "DB_HOST"            "$DB_HOST"
update_env "DB_PORT"            "$DB_PORT"
update_env "DB_DATABASE"        "$DB_DATABASE"
update_env "DB_USERNAME"        "$DB_USERNAME"
update_env "DB_PASSWORD"        "$DB_PASSWORD"
update_env "REDIS_HOST"         "$REDIS_HOST"
update_env "REDIS_PORT"         "$REDIS_PORT"
update_env "REDIS_PASSWORD"     "$REDIS_PASSWORD"
update_env "QUEUE_CONNECTION"   "$QUEUE_CONNECTION"
update_env "CACHE_STORE"        "$CACHE_STORE"
update_env "SESSION_DRIVER"     "$SESSION_DRIVER"
update_env "BROADCAST_CONNECTION" "$BROADCAST_CONNECTION"
update_env "REVERB_APP_ID"      "$REVERB_APP_ID"
update_env "REVERB_APP_KEY"     "$REVERB_APP_KEY"
update_env "REVERB_APP_SECRET"  "$REVERB_APP_SECRET"
update_env "REVERB_HOST"        "$REVERB_HOST"
update_env "REVERB_PORT"        "$REVERB_PORT"
update_env "REVERB_SCHEME"      "$REVERB_SCHEME"
update_env "MAIL_MAILER"        "$MAIL_MAILER"
update_env "MAIL_HOST"          "$MAIL_HOST"
update_env "MAIL_PORT"          "$MAIL_PORT"
update_env "MAIL_USERNAME"      "$MAIL_USERNAME"
update_env "MAIL_PASSWORD"      "$MAIL_PASSWORD"
update_env "MAIL_FROM_ADDRESS"  "$MAIL_FROM_ADDRESS"
update_env "GOOGLE_CLIENT_ID"   "$GOOGLE_CLIENT_ID"
update_env "GOOGLE_CLIENT_SECRET" "$GOOGLE_CLIENT_SECRET"
update_env "GOOGLE_REDIRECT_URI"  "$GOOGLE_REDIRECT_URI"

# ---------------------------------------------------------------------------
# 3. Generar APP_KEY si sigue vacía
# ---------------------------------------------------------------------------
CURRENT_KEY=$(grep "^APP_KEY=" /var/www/.env | cut -d'=' -f2)
if [ -z "$CURRENT_KEY" ] || [ "$CURRENT_KEY" = "base64:" ]; then
    echo "[init] Generando APP_KEY..."
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 4. Permisos
# ---------------------------------------------------------------------------
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ---------------------------------------------------------------------------
# 5. Esperar MySQL (check TCP)
# ---------------------------------------------------------------------------
DB_HOST_VAL="${DB_HOST:-db}"
DB_PORT_VAL="${DB_PORT:-3306}"
echo "[init] Esperando a ${DB_HOST_VAL}:${DB_PORT_VAL}..."
for i in $(seq 1 30); do
    if nc -z "$DB_HOST_VAL" "$DB_PORT_VAL" 2>/dev/null; then
        echo "[init] Base de datos lista."
        break
    fi
    echo "[init] Intento $i/30 - reintentando en 3s..."
    sleep 3
done

# ---------------------------------------------------------------------------
# 6. Migraciones
# ---------------------------------------------------------------------------
echo "[init] Ejecutando migraciones..."
php artisan migrate --force --no-interaction || echo "[warn] Migraciones fallaron"

# ---------------------------------------------------------------------------
# 7. Storage link
# ---------------------------------------------------------------------------
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link --force || true
fi

# ---------------------------------------------------------------------------
# 8. Cachear para producción
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
