# HotelLink con Docker

Con esto levantas HotelLink **sin instalar PHP, Composer, MySQL ni Node** en tu
máquina. Todo (backend Laravel, base de datos, worker de tareas y frontend React)
corre dentro de contenedores.

## 1. Requisito único: Docker Desktop

- **Windows / macOS:** instala [Docker Desktop](https://www.docker.com/products/docker-desktop/).
  Trae `docker` y `docker compose`. Déjalo abierto (el icono de la ballena arriba).
- **Linux:** instala Docker Engine + el plugin `docker-compose-v2`.

Comprueba que está listo:

```bash
docker --version
docker compose version
```

## 2. Primer arranque

Desde la carpeta del proyecto (donde está `compose.yaml`):

```bash
# 1. Crea tu archivo de configuración a partir del ejemplo
cp .env.example .env        # Windows PowerShell: copy .env.example .env

# 2. (Opcional) Pon tus credenciales SMTP reales en el .env (MAIL_*)

# 3. Construye y levanta todo
docker compose up -d --build
```

La primera vez tarda unos minutos: descarga las imágenes, instala las
dependencias de Composer y de npm, y prepara la base de datos. El contenedor
`back` hace la preparación solo al arrancar (ver `docker/entrypoint.sh`):

1. `composer install`
2. crea `.env` y genera `APP_KEY` si no existen
3. `php artisan migrate`
4. `php artisan db:seed` **solo si la base está vacía**
5. enlaza `public/storage`

Para ver el avance:

```bash
docker compose logs -f back     # backend Laravel
docker compose logs -f front    # frontend React (Vite)
```

Cuando el back diga `Listo: http://localhost:8080`, ya está arriba.

## 3. Dónde entrar

| Servicio | URL | Para qué |
|----------|-----|----------|
| **Portal React** (front) | http://localhost:5173 | el portal de reservas (SPA) |
| **Backend Laravel web01** (back) | http://localhost:8080 | el API REST + el sitio/panel Blade |
| **Backend Laravel web02** (web02) | http://localhost:8081 | segunda instancia del mismo backend (alta disponibilidad) |

> Si alguno de esos puertos ya lo usas, cámbialo en `.env`
> (`APP_PORT`, `WEB02_PORT`, `VITE_PORT`) y vuelve a `docker compose up -d`.

## 4. Comandos del día a día

```bash
docker compose up -d            # levantar
docker compose down             # apagar (la base de datos se conserva)
docker compose restart back     # reiniciar solo el backend
docker compose logs -f back     # ver registros del backend

# Ejecutar artisan / composer dentro del backend:
docker compose exec back php artisan migrate
docker compose exec back php artisan db:seed
docker compose exec back composer require vendor/paquete
docker compose exec back bash   # una terminal dentro del contenedor

# Pruebas automatizadas (usan su propia base SQLite, nunca la de desarrollo):
docker compose exec back php artisan test

# Frontend (dentro del contenedor front):
docker compose exec front npm install paquete
```

## 5. Contenedores que se levantan

Todos comparten la red bridge personalizada **`hotellink-net`** y se localizan
entre sí por el nombre del servicio (`base`, `back`, ...), nunca por IP.

- **back** (`hotellink-back`) — Laravel web01 (API REST + sitio/panel Blade). PHP 8.3 + Apache desde `public/`.
  Es el único que ejecuta migraciones y seeders al arrancar.
- **web02** (`hotellink-web02`) — segunda instancia web con la misma imagen `hotellink-app:1.0`.
  Arranca como réplica (`HOTELLINK_ROLE=replica`): solo espera la BD y sirve la app.
- **base** (`hotellink-base`) — MariaDB 10.11. **No publica ningún puerto** al host: solo es
  alcanzable dentro de `hotellink-net`. Los datos se guardan en el volumen nombrado
  `hotellink-dbdata`, así que **no se pierden** al apagar ni al recrear el contenedor.
- **worker** (`hotellink-worker`) — procesa tareas en segundo plano (`php artisan queue:work`), p. ej.
  los correos. Reutiliza la misma imagen que `back`.
- **front** (`hotellink-front`) — React (Vite dev server). Hace proxy de `/api` al `back` (sin CORS).

Bind mounts: el código (`.:/var/www/html`, lectura/escritura para desarrollo) y la
configuración de PHP (`docker/php/php.ini`, **solo lectura** `:ro`).

## 6. Base de datos

- Se crea sola con las credenciales del `.env`: base `DB_DATABASE`, usuario de
  aplicación `DOCKER_DB_USER` / `DOCKER_DB_PASSWORD` y contraseña root `DB_ROOT_PASSWORD`.
- La BD **no está publicada al host** (principio de mínima exposición). Para consultarla
  entra por el propio contenedor:

```bash
docker compose exec base mariadb -uhotellink -photellink_dev hotellink
# o con un cliente gráfico, publicando el puerto SOLO en tu PC con un override local:
#   docker-compose.override.yml ->  services: { base: { ports: ["127.0.0.1:3307:3306"] } }
```

- Para empezar de cero (borra TODOS los datos):

```bash
docker compose down -v          # -v elimina el volumen hotellink-dbdata
docker compose up -d --build
```

## 7. Correo

El envío usa **SMTP real** configurado en el `.env` (`MAIL_*`). Las credenciales
nunca se suben a git (el `.env` está en `.gitignore`). En desarrollo conviene usar
un SMTP de pruebas/sandbox para no enviar correos reales mientras pruebas.

## 8. Despliegue en servidor

Para llevar esto a una máquina virtual o servidor, ver
[`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).
