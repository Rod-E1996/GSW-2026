#!/bin/sh
# Prepara el proyecto HotelLink dentro del contenedor en cada arranque.
set -e

cd /var/www/html

DB_HOST="${DB_HOST:-db}"
DB_DATABASE="${DB_DATABASE:-hotellink}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-root}"

# --skip-ssl: el cliente MariaDB nuevo exige TLS por defecto y el servidor no lo usa.
mysql_cli() {
  mysql --skip-ssl -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$@"
}

wait_for_db() {
  echo "Esperando a la base de datos en ${DB_HOST}..."
  until mysql_cli -e "SELECT 1" >/dev/null 2>&1; do
    sleep 2
  done
  echo "Base de datos lista."
}

# El contenedor de colas (queue) solo necesita la BD arriba; no prepara el proyecto.
case "$1" in
  apache2-foreground) ;;
  *)
    wait_for_db
    exec "$@"
    ;;
esac

# --- A partir de aquí, preparación del contenedor principal (app) ---

# 1. Dependencias de Composer (solo si faltan, para que los reinicios sean rápidos).
if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias de Composer..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 2. Archivo .env y APP_KEY si no existen todavía.
if [ ! -f .env ]; then
  echo "Creando .env a partir de .env.example..."
  cp .env.example .env
fi
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "Generando APP_KEY..."
  php artisan key:generate --force
fi

# 3. Permisos de escritura para Laravel (dev).
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

wait_for_db

# 4. Migraciones.
echo "Ejecutando migraciones..."
php artisan migrate --force

# 5. Seeders solo si la base está vacía (primer arranque).
TABLES=$(mysql_cli -N -B \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND table_name='users';" 2>/dev/null || echo 0)
USERS=0
if [ "$TABLES" = "1" ]; then
  USERS=$(mysql_cli -N -B \
    -e "SELECT COUNT(*) FROM \`$DB_DATABASE\`.users;" 2>/dev/null || echo 0)
fi
if [ "${USERS:-0}" = "0" ]; then
  echo "Base vacía: ejecutando seeders..."
  php artisan db:seed --force
else
  echo "La base ya tiene datos: se omiten los seeders."
fi

# 6. Enlace public/storage.
if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

# 7. Limpiar y cachear configuración.
php artisan config:clear || true

echo "Listo: http://localhost:${APP_PORT:-8080}"

exec "$@"
