# HotelLink — Despliegue en un servidor (máquina virtual)

La idea: la **misma configuración de Docker** que usas en tu computadora corre en
el servidor. En la VM **no instalas PHP, Composer ni XAMPP** — solo Docker, y los
contenedores traen todo. Esto responde a "¿cómo hacemos que la VM sea donde se
aloje el sitio?": la VM ejecuta `docker compose up`, igual que tu máquina, pero
accesible desde internet.

```
   Tu PC (desarrollo)                 VM / Servidor (producción)
   ┌───────────────────┐  git push    ┌──────────────────────────┐
   │ edita el código   │ ───────────► │ git pull                 │
   │ docker compose up │              │ docker compose up -d      │
   │ localhost:8080    │              │ http://IP_DEL_SERVIDOR    │
   └───────────────────┘              └──────────────────────────┘
```

---

## Paso 1 — Conseguir la máquina virtual

Cualquiera de estas sirve; todas te dan una VM con Ubuntu y una **IP pública**:

- **En la nube:** DigitalOcean, AWS Lightsail/EC2, Google Cloud, Azure, Linode,
  Vultr, Contabo. Elige **Ubuntu 22.04 o 24.04 LTS**, mínimo **2 GB de RAM**
  (recomendado 4 GB porque corren varios contenedores).
- **En tu propia máquina / local:** VirtualBox o VMware con una ISO de Ubuntu
  Server, o un servidor físico en la oficina del hotel.

Anota: la **IP del servidor**, el **usuario** (normalmente `root` o `ubuntu`) y
cómo entrar por **SSH**.

---

## Paso 2 — Entrar y preparar la VM

Desde tu PC:

```bash
ssh usuario@IP_DEL_SERVIDOR
```

Ya dentro, instala **Docker** y **Git** (una sola vez):

```bash
# Docker (script oficial)
curl -fsSL https://get.docker.com | sh

# Que tu usuario pueda usar docker sin sudo
sudo usermod -aG docker $USER
# cierra sesión y vuelve a entrar para que tome efecto
exit
```

Vuelve a entrar por SSH y comprueba:

```bash
docker --version
docker compose version
git --version   # si no está: sudo apt-get update && sudo apt-get install -y git
```

> Eso es **todo** lo que se instala en la VM. PHP, MySQL y Composer viven dentro
> de los contenedores.

---

## Paso 3 — Llevar el proyecto a la VM

**Opción A — con Git (recomendada).** Sube el proyecto a GitHub/GitLab y en la VM:

```bash
git clone https://github.com/TU_USUARIO/hotellink.git
cd hotellink
```

Para actualizar en el futuro: `git pull` y vuelves a levantar (Paso 5).

**Opción B — copiar los archivos** (sin Git), desde tu PC:

```bash
# comprime, sube y descomprime (excluye lo pesado que se regenera)
scp -r ./GSW-2026 usuario@IP_DEL_SERVIDOR:~/hotellink
```

(Si usas rsync: `rsync -av --exclude vendor --exclude node_modules ./GSW-2026/ usuario@IP:~/hotellink/`)

---

## Paso 4 — Configurar el `.env` para producción

En la VM, dentro de la carpeta del proyecto:

```bash
cp .env.example .env
nano .env
```

Cambia al menos esto para producción:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP_DEL_SERVIDOR        # o http://reservas.tudominio.com

# Pon contraseñas FUERTES (no las de ejemplo)
DB_ROOT_PASSWORD=otra_contraseña_root_fuerte

# El sitio escucha en el puerto 80 (web normal)
APP_PORT=80

# Correo real del hotel (si no, deja Mailpit solo para pruebas)
# MAIL_HOST=smtp.tu-proveedor.com  etc.
```

> **Seguridad:** `APP_DEBUG=false` siempre en producción, y cambia TODAS las
> contraseñas de ejemplo. El `.env` nunca se sube a Git (ya está en
> `.gitignore` y `.dockerignore`).

---

## Paso 5 — Levantar el sitio

```bash
docker compose up -d --build
```

Igual que en tu PC: construye, instala dependencias, migra y (la primera vez)
ejecuta los seeders. Comprueba:

```bash
docker compose ps          # todos "running"/"healthy"
docker compose logs -f app # hasta ver "Listo: http://localhost:80"
```

Abre en el navegador: **http://IP_DEL_SERVIDOR**

Para que arranque solo si la VM se reinicia, los servicios ya tienen
`restart: unless-stopped` en `docker-compose.yml`. No hay que hacer nada más.

---

## Paso 6 — Abrir los puertos (firewall)

El sitio no se verá desde fuera hasta que el firewall deje pasar el puerto web.

- **En la VM (Ubuntu con UFW):**

  ```bash
  sudo ufw allow 22      # SSH, para no quedarte fuera
  sudo ufw allow 80      # el sitio
  sudo ufw allow 443     # HTTPS (Paso 8)
  sudo ufw enable
  ```

- **En el panel del proveedor de nube** (DigitalOcean, AWS, etc.): abre también
  los puertos **80** y **443** en el "firewall" / "security group" de la VM.

> **No abras** los puertos 8081 (phpMyAdmin), 8025 (Mailpit) ni 3307 (base de
> datos) a internet. En producción conviene **quitar** esos servicios del
> `compose` o dejarlos solo accesibles por un túnel SSH. Son herramientas de
> desarrollo.

---

## Paso 7 — Dominio (opcional)

Para entrar por `reservas.tuhotel.com` en vez de la IP:

1. En tu proveedor de dominio, crea un registro **A** que apunte el
   subdominio a la **IP del servidor**.
2. Pon ese dominio en `APP_URL` del `.env` y reinicia: `docker compose up -d`.

---

## Paso 8 — HTTPS (candado, muy recomendable)

Para servir en `https://` con certificado gratuito (Let's Encrypt) sin complicar
la app, se pone un **reverse proxy** delante. El más simple es
[Caddy](https://caddyserver.com/), que gestiona el certificado solo.

Resumen del montaje (se documenta en detalle al desplegar):

1. Cambia el puerto de la app a interno (`APP_PORT=8080`, no al 80).
2. Añade un servicio `caddy` al `compose` que escuche 80/443 y reenvíe a `app`.
3. Un `Caddyfile` de 2 líneas con tu dominio basta; Caddy saca el certificado
   automáticamente.

Alternativa clásica: Nginx + Certbot. Caddy es más corto para este caso.

---

## Paso 9 — Respaldos de la base de datos

Los datos viven en el volumen `dbdata` (persisten aunque apagues los
contenedores). Para un respaldo manual:

```bash
docker compose exec db sh -c \
  'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > backup_$(date +%F).sql
```

Restaurar:

```bash
docker compose exec -T db sh -c \
  'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < backup_2026-10-05.sql
```

> Recomendado: un `cron` en la VM que haga ese respaldo cada noche y lo copie a
> otro sitio (almacenamiento del proveedor, otro disco).

---

## Resumen del ciclo de trabajo

| En tu PC | En la VM (una vez) | En la VM (cada despliegue) |
|----------|--------------------|-----------------------------|
| desarrollas y pruebas con Docker | instalar Docker + Git | `git pull` |
| `git push` | `git clone` + `.env` | `docker compose up -d --build` |
| | abrir firewall 80/443 | `docker compose exec app php artisan migrate --force` |

Con esto, la VM es el servidor donde se aloja HotelLink: recibe el tráfico en su
IP/dominio y sirve el sitio desde los mismos contenedores que usas localmente,
sin PHP ni XAMPP instalados en ella.
