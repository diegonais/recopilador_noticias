<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$apiEndpoint = '/api/news.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Portal simple para visualizar noticias recientes de la Agencia Boliviana de Informacion.">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div
        class="site-shell"
        data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
        data-timezone="<?php echo htmlspecialchars($config->timezone(), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <header class="site-header">

            <div class="brand">
                <div class="brand-mark" aria-hidden="true">ABI</div>
                <div>
                    <h1><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>
            <p class="hero-copy">
                Noticias recientes obtenidas de la Agencia Boliviana de Informacion (ABI).
            </p>
        </header>

        <main class="content">
            <section class="content-head">
                <div>
                    <p class="section-kicker">&Uacute;ltimas publicaciones</p>
                </div>
                <p id="last-updated" class="last-updated" aria-live="polite"></p>
            </section>

            <div class="toolbar-actions">
                <button id="filter-toggle" class="filter-toggle" type="button" aria-expanded="false" aria-controls="filters-panel">
                    Buscar por fecha
                </button>
                <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Cambiar a tema oscuro" aria-pressed="false">&#9790;</button>
            </div>

            <section id="filters-panel" class="filters-card filters-panel filters-panel--collapsed" aria-label="Filtros de noticias" aria-hidden="true">
                <div class="calendar-filter" aria-label="Calendario de noticias">
                    <div class="calendar-filter__header">
                        <button id="calendar-prev" class="calendar-filter__nav" type="button" aria-label="Mes anterior">
                            <svg class="calendar-filter__nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 6l-6 6l6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button id="calendar-current-month" class="calendar-filter__title-trigger" type="button" aria-label="Seleccionar mes y a&ntilde;o" aria-haspopup="true" aria-expanded="false" aria-controls="calendar-month-year-picker"></button>
                        <button id="calendar-next" class="calendar-filter__nav" type="button" aria-label="Mes siguiente">
                            <svg class="calendar-filter__nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 6l6 6l-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                    <div id="calendar-month-year-picker" class="calendar-filter__picker is-hidden" aria-hidden="true">
                        <label class="calendar-filter__picker-field" for="calendar-year-select">
                            <span>A&ntilde;o</span>
                            <select id="calendar-year-select" class="calendar-filter__picker-select"></select>
                        </label>
                        <label class="calendar-filter__picker-field" for="calendar-month-select">
                            <span>Mes</span>
                            <select id="calendar-month-select" class="calendar-filter__picker-select"></select>
                        </label>
                    </div>
                    <div class="calendar-filter__weekdays" aria-hidden="true">
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mie</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sab</span>
                        <span>Dom</span>
                    </div>
                    <div id="calendar-grid" class="calendar-filter__grid" role="grid" aria-label="Dias del mes"></div>
                </div>

                <div class="filters-actions">
                    <button id="filter-today" class="filter-action" type="button">Hoy</button>
                </div>
            </section>

            <div id="loading-state" class="status-card">
                <strong>Cargando noticias...</strong>
                <p>Estamos consultando las noticias almacenadas.</p>
            </div>

            <div id="error-state" class="status-card is-hidden is-error" role="alert">
                <strong>No fue posible cargar las noticias.</strong>
                <p>Intenta nuevamente en unos minutos.</p>
            </div>

            <div id="empty-state" class="status-card is-hidden">
                <strong>Todav&iacute;a no hay noticias disponibles.</strong>
                <p id="empty-state-message">Cuando el sistema vuelva a actualizarse, las publicaciones apareceran aqui.</p>
            </div>

            <section id="news-list" class="news-grid is-hidden" aria-live="polite" aria-busy="true"></section>
        </main>

        <footer class="site-footer">
            <div class="site-footer__inner">
                <div class="site-footer__brand">
                    <p class="site-footer__label">Recopilador Informativo</p>
                    <h2 class="site-footer__title"><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="site-footer__copy">Cobertura autom&aacute;tica de noticias ABI con visualizaci&oacute;n optimizada para consulta diaria.</p>
                </div>

                <div class="site-footer__meta" aria-label="Informacion del portal">
                    <p><span>Fuente ::</span> ABI RSS oficial</p>
                    <p><span>Frecuencia ::</span> Actualizaci&oacute;n cada 5 minutos</p>
                    <p><span>Desarrollo ::</span> <?php echo htmlspecialchars($config->footerAuthor(), ENT_QUOTES, 'UTF-8'); ?> | <?php echo date('Y'); ?></p>
                </div>
            </div>
        </footer>
    </div>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">Subir</button>

    <noscript>
        <div class="noscript-message">Este portal necesita JavaScript habilitado para mostrar las noticias.</div>
    </noscript>

    <script src="./assets/js/news-shared.js" defer></script>
    <script src="./assets/js/app.js" defer></script>
</body>
</html>

