# HotelLink con Docker

Con esto levantas HotelLink **sin instalar PHP, Composer, XAMPP ni MySQL** en tu
máquina. Todo (servidor web + PHP, base de datos, phpMyAdmin y un buzón de correo
de prueba) corre dentro de contenedores.

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

Desde la carpeta del proyecto (donde está `docker-compose.yml`):

```bash
# 1. Crea tu archivo de configuración a partir del ejemplo
cp .env.example .env        # Windows PowerShell: copy .env.example .env

# 2. Construye y levanta todo
docker compose up -d --build
```

La primera vez tarda unos minutos: descarga las imágenes, instala las
dependencias de Composer y prepara la base de datos. El contenedor `app` hace
todo esto solo al arrancar (ver `docker/entrypoint.sh`):

1. `composer install`
2. crea `.env` y genera `APP_KEY` si no existen
3. `php artisan migrate`
4. `php artisan db:seed` **solo si la base está vacía**
5. enlaza `public/storage`

Para ver el avance de esa preparación:

```bash
docker compose logs -f app
```

Cuando veas `Listo: http://localhost:8080`, ya está arriba.

## 3. Dónde entrar

| Servicio | URL | Para qué |
|----------|-----|----------|
| **Sitio HotelLink** | http://localhost:8080 | la aplicación |
| **phpMyAdmin** | http://localhost:8081 | ver y editar la base de datos (usuario `root`, contraseña `root`) |
| **Mailpit** | http://localhost:8025 | ver los correos que envía el sistema (no salen a internet) |

> Si alguno de esos puertos ya lo usas, cámbialo en `.env`
> (`APP_PORT`, `PMA_PORT`, `MAILPIT_PORT`, `DB_FORWARD_PORT`) y vuelve a
> `docker compose up -d`.

## 4. Comandos del día a día

```bash
docker compose up -d            # levantar
docker compose down             # apagar (la base de datos se conserva)
docker compose restart app      # reiniciar solo la app
docker compose logs -f app      # ver registros

# Ejecutar artisan / composer dentro del contenedor:
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app composer require vendor/paquete
docker compose exec app bash    # una terminal dentro del contenedor
```

## 5. Contenedores que se levantan

- **app** — PHP 8.3 + Apache. Sirve el sitio desde `public/`.
- **db** — MariaDB 10.11 (el mismo motor que trae XAMPP). Los datos se guardan
  en un volumen de Docker llamado `dbdata`, así que **no se pierden** al apagar.
- **queue** — procesa los correos en segundo plano (`php artisan queue:work`).
  Se usa cuando en el `.env` tienes `QUEUE_CONNECTION=database`.
- **phpmyadmin** — administrador visual de la base de datos.
- **mailpit** — captura los correos de prueba (nuevo dispositivo, errores, etc.).

## 6. Base de datos

- Se crea sola con las credenciales del `.env` (`DB_DATABASE`) y la contraseña
  root (`DB_ROOT_PASSWORD`).
- Desde tu máquina, si quieres conectarte con un cliente externo (DBeaver,
  HeidiSQL), usa `127.0.0.1` puerto **3307** (`DB_FORWARD_PORT`), usuario `root`.
- Para empezar de cero (borra TODOS los datos):

```bash
docker compose down -v          # -v elimina el volumen dbdata
docker compose up -d --build
```

## 7. Convivir con XAMPP

El mismo `.env` sirve para los dos entornos. `docker-compose.yml` sobreescribe
`DB_HOST=db`, la contraseña de BD y el correo **solo dentro de los contenedores**,
así que puedes seguir abriendo el proyecto con XAMPP (`DB_HOST=127.0.0.1`) sin
tocar nada. La base de datos de Docker y la de XAMPP son independientes.

## 8. Despliegue en servidor

Para llevar esto a una máquina virtual o servidor, ver
[`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).
