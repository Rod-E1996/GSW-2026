#!/bin/sh
# Prepara el proyecto HotelLink dentro del contenedor en cada arranque.
set -e

cd /var/www/html

# Lo que creen los procesos de root (seeders, worker) debe poder escribirlo Apache (www-data).
umask 0000

DB_HOST="${DB_HOST:-base}"
DB_DATABASE="${DB_DATABASE:-hotellink}"
DB_USERNAME="${DB_USERNAME:-hotellink}"
DB_PASSWORD="${DB_PASSWORD:-hotellink_dev}"

# --skip-ssl: el cliente MariaDB nuevo exige TLS por defecto y el servidor no lo usa.
mysql_cli() {
  mysql --skip-ssl -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$@"
}

wait_for_db() {
  echo "[${HOTELLINK_NODE:-app}] Esperando a la base de datos en ${DB_HOST}..."
  until mysql_cli -e "SELECT 1" >/dev/null 2>&1; do
    sleep 2
  done
  echo "[${HOTELLINK_NODE:-app}] Base de datos lista."
}

# Permisos de escritura para Laravel (dev; Apache corre como www-data y el
# bind-mount de Windows no le da escritura, por eso 777 en dev).
fix_perms() {
  chmod -R 777 storage bootstrap/cache 2>/dev/null || true
}

# El worker (queue) y las replicas web (HOTELLINK_ROLE=replica) solo necesitan la BD arriba;
# no preparan el proyecto: eso lo hace una sola vez el contenedor principal (web01).
if [ "$1" != "apache2-foreground" ] || [ "${HOTELLINK_ROLE:-primary}" = "replica" ]; then
  fix_perms
  wait_for_db
  exec "$@"
fi

# --- A partir de aqui, preparacion del contenedor principal (web01) ---

# 1. Dependencias de Composer (solo si faltan, para que los reinicios sean rapidos).
if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias de Composer..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 2. Archivo .env y APP_KEY si no existen todavia.
if [ ! -f .env ]; then
  echo "Creando .env a partir de .env.example..."
  cp .env.example .env
fi
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "Generando APP_KEY..."
  php artisan key:generate --force
fi

fix_perms
wait_for_db

# 3. Migraciones.
echo "Ejecutando migraciones..."
php artisan migrate --force

# 4. Seeders solo si la base esta vacia (primer arranque).
TABLES=$(mysql_cli -N -B \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND table_name='users';" 2>/dev/null || echo 0)
USERS=0
if [ "$TABLES" = "1" ]; then
  USERS=$(mysql_cli -N -B \
    -e "SELECT COUNT(*) FROM \`$DB_DATABASE\`.users;" 2>/dev/null || echo 0)
fi
if [ "${USERS:-0}" = "0" ]; then
  echo "Base vacia: ejecutando seeders..."
  php artisan db:seed --force
else
  echo "La base ya tiene datos: se omiten los seeders."
fi

# 5. Enlace public/storage.
if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

# 6. Limpiar configuracion cacheada.
php artisan config:clear || true

# Lo creado por migraciones y seeders (cache, logs) queda escribible para Apache.
fix_perms

echo "Listo: http://localhost:${APP_PORT:-8080}"

exec "$@"
