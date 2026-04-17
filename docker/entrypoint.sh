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
mkdir -p /var/secrets

# ---------------------------------------------------------------------------
# 2. Cargar o generar secretos persistentes
#    Se guardan en /var/secrets (volumen Docker) para sobrevivir reinicios
# ---------------------------------------------------------------------------
SECRETS_FILE="/var/secrets/app.env"

if [ ! -f "$SECRETS_FILE" ]; then
    echo "[init] Primer arranque: generando secretos..."
    APP_KEY_GEN=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    REVERB_KEY_GEN=$(php -r "echo bin2hex(random_bytes(20));")
    REVERB_SECRET_GEN=$(php -r "echo bin2hex(random_bytes(32));")
    cat > "$SECRETS_FILE" <<SECRETS
APP_KEY=${APP_KEY_GEN}
REVERB_APP_KEY=${REVERB_KEY_GEN}
REVERB_APP_SECRET=${REVERB_SECRET_GEN}
SECRETS
    echo "[init] Secretos generados y guardados."
fi

# Cargar secretos persistidos
# shellcheck disable=SC1090
source "$SECRETS_FILE"

# ---------------------------------------------------------------------------
# 3. Preparar entorno global si es necesario
#    Laravel lee las variables de entorno inyectadas por Docker.
# ---------------------------------------------------------------------------
if [ ! -f "/var/www/.env" ]; then
    echo "[init] .env no existe, copiando desde .env.example..."
    cp /var/www/.env.example /var/www/.env
fi

# Inyectar las vars importantes en .env desde las variables del OS (inyectadas por docker-compose)
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=${DB_CONNECTION:-mysql}/" /var/www/.env
sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST:-db}/" /var/www/.env
sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT:-3306}/" /var/www/.env
sed -i "s/^REDIS_HOST=.*/REDIS_HOST=${REDIS_HOST:-redis}/" /var/www/.env
sed -i "s/^REDIS_PORT=.*/REDIS_PORT=${REDIS_PORT:-6379}/" /var/www/.env
sed -i "s/^APP_KEY=.*/APP_KEY=${APP_KEY}/" /var/www/.env
sed -i "s/^SESSION_DRIVER=.*/SESSION_DRIVER=${SESSION_DRIVER:-redis}/" /var/www/.env
sed -i "s/^CACHE_STORE=.*/CACHE_STORE=${CACHE_STORE:-redis}/" /var/www/.env
sed -i "s/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}/" /var/www/.env

echo "[init] Entorno configurado correctamente."

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
# 6. Garantizar usuario MySQL (resiste volumenes obsoletos)
#    Si el volumen db_data viene de un despliegue anterior, MySQL ignora los
#    env vars MYSQL_USER/MYSQL_PASSWORD y el usuario puede no existir o tener
#    credenciales distintas. Conectamos como root y lo reparamos.
# ---------------------------------------------------------------------------
MYSQL_ROOT_PASS="${DB_ROOT_PASSWORD:-np_root_secret_2026}"
DB_NAME="${DB_DATABASE:-notesproyects}"
DB_USER="${DB_USERNAME:-laravel}"
DB_PASS="${DB_PASSWORD:-np_db_secret_2026}"

echo "[init] Verificando usuario MySQL '$DB_USER'..."
mysql -h "$DB_HOST_VAL" -P "$DB_PORT_VAL" -u root -p"${MYSQL_ROOT_PASS}" \
    --connect-timeout=10 --silent 2>/dev/null <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

if [ $? -eq 0 ]; then
    echo "[init] Usuario MySQL OK."
else
    echo "[warn] No se pudo reparar el usuario MySQL (continuando de todos modos)."
fi

# ---------------------------------------------------------------------------
# 7. Migraciones
# ---------------------------------------------------------------------------
echo "[init] Ejecutando migraciones..."
php artisan migrate --force --no-interaction || echo "[warn] Migraciones fallaron"

# ---------------------------------------------------------------------------
# 8. Storage link
# ---------------------------------------------------------------------------
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link --force || true
fi

# ---------------------------------------------------------------------------
# 9. Cachear configuracion de produccion
# ---------------------------------------------------------------------------
echo "[init] Cacheando configuracion..."
php artisan config:cache || true
php artisan view:cache   || true
php artisan event:cache  || true
# NOTA: route:cache se omite intencionalmente — Livewire v4 usa rutas dinamicas
# que no se recuperan bien del cache en todas las configuraciones.

echo "[init] Iniciando supervisord..."
echo "============================================"
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
