# Portal Noticias ABI

Portal web simple en PHP puro que consume el RSS oficial de la Agencia Boliviana de Informacion (ABI), procesa las noticias y las almacena localmente en `storage/news.json` para servirlas desde una API liviana y mostrarlas en una interfaz responsive.

## Objetivo

- Obtener noticias del RSS oficial de ABI.
- Normalizar y deduplicar la informacion.
- Guardar el resultado localmente en JSON.
- Exponer las noticias mediante `api/news.php`.
- Mostrar el contenido en `public/index.php`.
- Actualizar el archivo cada 6 horas con un script ejecutable por cron.

## Estructura

```text
portal-noticias-abi/
â”œâ”€â”€ api/
â”‚   â””â”€â”€ news.php
â”œâ”€â”€ app/
â”‚   â”œâ”€â”€ config/
â”‚   â”‚   â””â”€â”€ config.php
â”‚   â”œâ”€â”€ controllers/
â”‚   â”‚   â””â”€â”€ NewsController.php
â”‚   â”œâ”€â”€ helpers/
â”‚   â”‚   â”œâ”€â”€ DateHelper.php
â”‚   â”‚   â”œâ”€â”€ ResponseHelper.php
â”‚   â”‚   â””â”€â”€ TextHelper.php
â”‚   â””â”€â”€ services/
â”‚       â”œâ”€â”€ AbiRssService.php
â”‚       â”œâ”€â”€ NewsService.php
â”‚       â””â”€â”€ StorageService.php
â”œâ”€â”€ cron/
â”‚   â””â”€â”€ cron.txt
â”œâ”€â”€ public/
â”‚   â”œâ”€â”€ assets/
â”‚   â”‚   â”œâ”€â”€ css/
â”‚   â”‚   â”‚   â””â”€â”€ styles.css
â”‚   â”‚   â”œâ”€â”€ img/
â”‚   â”‚   â”‚   â””â”€â”€ logo.png
â”‚   â”‚   â””â”€â”€ js/
â”‚   â”‚       â””â”€â”€ app.js
â”‚   â””â”€â”€ index.php
â”œâ”€â”€ scripts/
â”‚   â””â”€â”€ update_news.php
â”œâ”€â”€ storage/
â”‚   â”œâ”€â”€ cache/
â”‚   â”œâ”€â”€ logs/
â”‚   â”‚   â””â”€â”€ update.log
â”‚   â””â”€â”€ news.json
â”œâ”€â”€ .env
â”œâ”€â”€ .gitignore
â””â”€â”€ README.md
```

## Requisitos

- PHP 8.0 o superior recomendado.
- Acceso saliente a internet para leer el feed RSS de ABI.
- Permisos de escritura sobre `storage/`.

## Configuracion

El archivo `.env` permite ajustar opciones basicas:

```env
APP_NAME="Portal Noticias ABI"
TIMEZONE="America/La_Paz"
ABI_RSS_URL="https://abi.bo/feed/"
MAX_NEWS_ITEMS=60
```

## Ejecucion local

Desde la raiz del proyecto puedes levantar un servidor embebido de PHP:

```bash
php -S localhost:8000 -t .
```

Luego abre:

```text
http://localhost:8000/public/index.php
```

## Ejecucion con Docker

El proyecto incluye un contenedor listo para levantar el portal en el puerto `3003`:

```bash
docker compose up --build -d
```

Luego abre:

```text
http://localhost:3003
```

Detalles del contenedor:

- sirve la aplicacion con el servidor embebido de PHP;
- expone `public/index.php` en `/`;
- mantiene disponible la API en `/api/news.php`;
- ejecuta una actualizacion inicial al arrancar;
- deja configurado el refresco automatico cada 6 horas dentro del contenedor.

### Desarrollo con cambios en vivo

El `docker-compose.yml` ahora monta el proyecto local dentro del contenedor con un `bind mount`:

- los cambios en archivos PHP, CSS y JavaScript se reflejan al refrescar el navegador;
- normalmente ya no necesitas reconstruir la imagen por cada cambio;
- si el navegador conserva assets viejos, usa `Ctrl + F5`.

Flujo recomendado de desarrollo:

```bash
docker compose up -d
```

Usa reconstruccion solo cuando cambies cosas del contenedor, por ejemplo:

- `Dockerfile`
- paquetes del sistema instalados en la imagen
- scripts de arranque del contenedor

En esos casos usa:

```bash
docker compose up --build -d
```

Para detenerlo:

```bash
docker compose down
```

## Actualizacion manual de noticias

Para obtener noticias nuevas y regenerar `storage/news.json`:

```bash
php scripts/update_news.php
```

El script:

- consulta el RSS oficial de ABI;
- normaliza las noticias al formato interno;
- evita duplicados usando `guid` y `link`;
- mezcla nuevas y existentes;
- ordena por fecha descendente;
- limita la cantidad total segun `MAX_NEWS_ITEMS`;
- registra el resultado en `storage/logs/update.log`.

Si el RSS falla o responde de forma invalida, el sistema conserva el JSON existente para evitar perder noticias ya almacenadas.

## API

La API local devuelve JSON desde:

```text
/api/news.php
```

Respuesta esperada:

```json
{
  "success": true,
  "count": 2,
  "updated_at": "2026-03-17T10:30:00-04:00",
  "data": [
    {
      "title": "Titulo",
      "summary": "Resumen corto",
      "link": "https://abi.bo/...",
      "source": "ABI",
      "published_at": "2026-03-17T10:00:00-04:00",
      "image": "",
      "guid": "..."
    }
  ]
}
```

## Cron

En `cron/cron.txt` hay un ejemplo para ejecutar el actualizador cada 6 horas:

```cron
0 */6 * * * /usr/bin/php /ruta-completa/portal-noticias-abi/scripts/update_news.php
```

## Flujo general

1. `scripts/update_news.php` consume el RSS oficial de ABI.
2. `AbiRssService` parsea y mapea cada noticia.
3. `NewsService` valida, deduplica, ordena y limita.
4. `StorageService` guarda el resultado en `storage/news.json` y registra logs.
5. `api/news.php` expone las noticias almacenadas.
6. `public/assets/js/app.js` consume la API y renderiza las tarjetas en `public/index.php`.

## Notas

- No se usan frameworks ni Composer.
- No se utiliza base de datos.
- El frontend solo muestra noticias ya procesadas y guardadas localmente.
- El proyecto esta preparado para tolerar errores del RSS sin romper la aplicacion completa.
