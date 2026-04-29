<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$apiEndpoint = '/api/news.php';
$newsId = isset($_GET['id']) ? (string) $_GET['id'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de noticia | <?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Lectura completa de una noticia ABI dentro del portal informativo.">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <header class="site-header detail-header">
        <div class="site-header__main">
            <div class="site-header__main-inner">
                <div class="brand">
                    <div class="brand-mark" aria-hidden="true">ABI</div>
                    <div>
                        <h1>Lectura completa</h1>
                    </div>
                </div>
                <p class="hero-copy">Lee la publicaci&oacute;n con una composici&oacute;n limpia y acceso directo a la fuente oficial para contrastar el contenido.</p>
            </div>
        </div>
    </header>

    <div class="site-shell detail-shell" data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>" data-news-id="<?php echo htmlspecialchars($newsId, ENT_QUOTES, 'UTF-8'); ?>">

        <main class="content">
            <!-- <nav class="detail-nav">
                <a class="detail-back" href="/">Volver al portal</a>
            </nav> -->

            <div id="detail-loading" class="status-card">
                <strong>Preparando lectura</strong>
                <p>Buscando la publicaci&oacute;n seleccionada.</p>
            </div>

            <div id="detail-error" class="status-card is-hidden is-error" role="alert">
                <strong>No se pudo abrir esta publicaci&oacute;n.</strong>
                <p>El enlace puede haber cambiado o la noticia a&uacute;n no fue sincronizada.</p>
            </div>

            <article id="news-detail" class="detail-article is-hidden"></article>
        </main>

    </div>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__brand">
                <p class="site-footer__label">Centro informativo</p>
                <h2 class="site-footer__title"><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="site-footer__copy">Seguimiento organizado de publicaciones ABI para lectura y consulta diaria.</p>
            </div>

            <div class="site-footer__meta" aria-label="Informacion del portal">
                <p><span>Fuente</span> ABI RSS oficial</p>
                <p><span>Ritmo</span> Actualizaci&oacute;n cada 5 minutos</p>
                <p><span>Equipo</span> <?php echo htmlspecialchars($config->footerAuthor(), ENT_QUOTES, 'UTF-8'); ?> | <?php echo date('Y'); ?></p>
            </div>
        </div>
    </footer>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">
        <svg class="back-to-top__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 19V5"></path>
            <path d="M5 12l7-7l7 7"></path>
        </svg>
    </button>

    <script src="./assets/js/news-shared.js" defer></script>
    <script src="./assets/js/news-detail.js" defer></script>
</body>
</html>

