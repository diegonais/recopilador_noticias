<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/DateHelper.php';
require_once __DIR__ . '/../app/helpers/TextHelper.php';
require_once __DIR__ . '/../app/services/AbiRssService.php';
require_once __DIR__ . '/../app/services/NewsService.php';
require_once __DIR__ . '/../app/services/StorageService.php';

$storageService = new StorageService();
$rssService = new AbiRssService();
$newsService = new NewsService();

$existingNews = $storageService->readNews();
$result = $rssService->fetchNews();

if (!isset($result['success']) || $result['success'] !== true) {
    $error = isset($result['error']) ? $result['error'] : 'Error desconocido al leer el feed RSS.';
    $storageService->logUpdate(0, 0, $error);

    fwrite(STDERR, '[ERROR] ' . $error . PHP_EOL);
    exit(1);
}

$fetchedItems = isset($result['items']) && is_array($result['items']) ? $result['items'] : array();
$processedIncoming = $newsService->normalizeCollection($fetchedItems);

if (count($fetchedItems) > 0 && count($processedIncoming) === 0) {
    $message = 'El feed respondio, pero ninguna noticia paso la validacion. Se conservo el archivo existente.';
    $storageService->logUpdate(count($fetchedItems), 0, $message);

    fwrite(STDERR, '[WARN] ' . $message . PHP_EOL);
    exit(1);
}

if (count($fetchedItems) === 0 && count($existingNews) > 0) {
    $message = 'El feed respondio sin noticias. Se conservo el archivo existente para evitar sobrescribirlo con vacio.';
    $storageService->logUpdate(0, 0, $message);

    fwrite(STDOUT, '[INFO] ' . $message . PHP_EOL);
    exit(0);
}

$newCount = $newsService->countNewItems($existingNews, $processedIncoming);
$finalNews = $newsService->mergeNews($existingNews, $processedIncoming);

if (!$storageService->saveNews($finalNews)) {
    $error = 'No se pudo guardar el archivo de noticias.';
    $storageService->logUpdate(count($fetchedItems), 0, $error);

    fwrite(STDERR, '[ERROR] ' . $error . PHP_EOL);
    exit(1);
}

$storageService->logUpdate(count($fetchedItems), $newCount);

fwrite(
    STDOUT,
    sprintf(
        '[OK] %s | obtenidas=%d | nuevas=%d | almacenadas=%d%s',
        DateHelper::nowForLog(),
        count($fetchedItems),
        $newCount,
        count($finalNews),
        PHP_EOL
    )
);