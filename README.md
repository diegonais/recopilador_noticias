# Portal Noticias ABI

Portal web en PHP 8.2 que consume el RSS oficial de la Agencia Boliviana de Informacion (ABI), normaliza y deduplica noticias, las almacena en JSON local o en Supabase y las expone mediante una API liviana y una interfaz responsive.

## Estructura

```text
api/
  news.php
bin/
  update-news.php
  migrate-news-to-supabase.php
bootstrap/
  app.php
  autoload.php
public/
  index.php
  news.php
  assets/
scripts/
  update_news.php
  migrate_news_to_supabase.php
src/
  News/
    Application/
    Domain/
    Infrastructure/
  Shared/
    Config/
    Http/
    Infrastructure/
    Support/
storage/
```

## Criterio de arquitectura

- `Application`: casos de uso orquestados, sin detalles de infraestructura.
- `Domain`: contratos y entidad `NewsItem`.
- `Infrastructure`: RSS ABI, persistencia JSON/Supabase, cache de paginas y logging.
- `Shared`: configuracion, HTTP y utilidades transversales.
- `bootstrap/`: composicion de dependencias y autoload.
- `bin/`: comandos principales del proyecto.
- `scripts/`: wrappers de compatibilidad para no romper flujos existentes.

## Configuracion

El archivo `.env` permite ajustar opciones basicas:

```env
APP_NAME="Portal Noticias ABI"
TIMEZONE="America/La_Paz"
ABI_RSS_URL="https://abi.bo/feed/"
MAX_NEWS_ITEMS=60
FOOTER_AUTHOR="Diego"
SUPABASE_ENABLED=false
SUPABASE_URL="https://tu-proyecto.supabase.co"
SUPABASE_SERVICE_ROLE_KEY="tu-service-role-key"
SUPABASE_TABLE="news"
```

Si `SUPABASE_ENABLED=true`, el sistema intentara leer desde Supabase y mantener `storage/news.json` como respaldo local sincronizado.

## Actualizacion manual de noticias

```bash
php bin/update-news.php
```

Tambien se conserva el wrapper anterior:

```bash
php scripts/update_news.php
```

## Migracion a Supabase

```bash
php bin/migrate-news-to-supabase.php
```

Wrapper compatible:

```bash
php scripts/migrate_news_to_supabase.php
```

## SOLID aplicado

- Responsabilidad unica: la extraccion RSS, la persistencia, el logging y los casos de uso quedaron separados.
- Abierto/cerrado: se pueden agregar nuevas fuentes o repositorios implementando contratos del dominio.
- Inversion de dependencias: los casos de uso dependen de interfaces, no de implementaciones concretas.
- Presentacion limpia: los entrypoints (`public`, `api`, `bin`) solo coordinan, no contienen logica de negocio.

