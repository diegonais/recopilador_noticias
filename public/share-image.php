<?php

declare(strict_types=1);

use PortalNoticias\News\Domain\NewsItem;

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$newsId = isset($_GET['id']) ? (string) $_GET['id'] : '';
$newsItem = null;

if ($newsId !== '') {
    try {
        $newsItem = resolveShareImageNewsItem($container->jsonNewsRepository()->findLatest(PHP_INT_MAX), $newsId);

        if (!$newsItem instanceof NewsItem) {
            $newsItem = resolveShareImageNewsItem($container->newsRepository()->findLatest(max(200, $config->maxNewsItems() * 3)), $newsId);
        }
    } catch (Throwable $error) {
        $newsItem = null;
    }
}

$imageUrl = $newsItem instanceof NewsItem ? trim($newsItem->image()) : '';

if ($imageUrl === '' || !isAllowedShareImageUrl($imageUrl)) {
    serveFallbackShareImage();
    exit;
}

$cachePath = $config->cachePath() . DIRECTORY_SEPARATOR . 'share_image_' . sha1($imageUrl);
$cacheMetaPath = $cachePath . '.meta';
$cachedType = is_file($cacheMetaPath) ? trim((string) file_get_contents($cacheMetaPath)) : '';

if (is_file($cachePath) && is_readable($cachePath) && filemtime($cachePath) !== false && time() - filemtime($cachePath) < 86400) {
    serveImageBytes((string) file_get_contents($cachePath), $cachedType !== '' ? $cachedType : 'image/jpeg');
    exit;
}

$response = fetchRemoteShareImage($imageUrl);

if ($response === null) {
    serveFallbackShareImage();
    exit;
}

[$contentType, $body] = $response;

if (!is_dir(dirname($cachePath))) {
    mkdir(dirname($cachePath), 0775, true);
}

@file_put_contents($cachePath, $body, LOCK_EX);
@file_put_contents($cacheMetaPath, $contentType, LOCK_EX);

serveImageBytes($body, $contentType);

function resolveShareImageNewsItem(array $items, string $newsId): ?NewsItem
{
    foreach ($items as $item) {
        if (!$item instanceof NewsItem) {
            continue;
        }

        if (shareImageIdentifiersMatch((string) $item->guid(), $newsId) || shareImageIdentifiersMatch((string) $item->link(), $newsId)) {
            return $item;
        }
    }

    return null;
}

function shareImageIdentifiersMatch(string $left, string $right): bool
{
    if (trim($left) === '' || trim($right) === '') {
        return false;
    }

    return in_array(shareImageNormalizeIdentifier($left), [
        shareImageNormalizeIdentifier($right),
        shareImageNormalizeIdentifier(rawurldecode($right)),
        shareImageNormalizeIdentifier(rawurldecode(rawurldecode($right))),
    ], true);
}

function shareImageNormalizeIdentifier(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    $parsed = parse_url($trimmed);

    if (!is_array($parsed) || !isset($parsed['host'])) {
        return $trimmed;
    }

    $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'https';
    $host = strtolower((string) $parsed['host']);
    $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
    $path = $path !== '/' ? rtrim($path, '/') : '/';
    $query = isset($parsed['query']) ? (string) $parsed['query'] : '';

    return $scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : '');
}

function isAllowedShareImageUrl(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

/**
 * @return array{0:string,1:string}|null
 */
function fetchRemoteShareImage(string $url): ?array
{
    if (function_exists('curl_init')) {
        $response = executeCurlShareImageRequest($url, true);

        if ($response === null && isAbiShareImageUrl($url)) {
            $response = executeCurlShareImageRequest($url, false);
        }

        if ($response !== null) {
            return $response;
        }
    }

    $headers = [
        'User-Agent: Portal Noticias ABI Share Image Proxy/1.0',
    ];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 18,
            'header' => implode("\r\n", $headers),
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $contentType = extractShareImageContentType(isset($http_response_header) && is_array($http_response_header) ? $http_response_header : []);

    if (is_string($body) && $body !== '') {
        $detectedType = resolveShareImageContentType($contentType, $body);

        if ($detectedType !== '') {
            return [$detectedType, $body];
        }
    }

    return null;
}

/**
 * @return array{0:string,1:string}|null
 */
function executeCurlShareImageRequest(string $url, bool $verifyPeer): ?array
{
    $handle = curl_init($url);

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_USERAGENT => 'Portal Noticias ABI Share Image Proxy/1.0',
        CURLOPT_SSL_VERIFYPEER => $verifyPeer,
        CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
    ]);

    $body = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);

    curl_close($handle);

    if (is_string($body) && $body !== '' && $statusCode >= 200 && $statusCode < 400) {
        $detectedType = resolveShareImageContentType($contentType, $body);

        if ($detectedType !== '') {
            return [$detectedType, $body];
        }
    }

    return null;
}

function isAbiShareImageUrl(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return in_array($host, ['abi.bo', 'www.abi.bo'], true);
}

function extractShareImageContentType(array $headers): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, 'Content-Type:') === 0) {
            return trim(substr((string) $header, strlen('Content-Type:')));
        }
    }

    return '';
}

function isShareImageContentType(string $contentType): bool
{
    return in_array(normalizeShareImageContentType($contentType), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
}

function resolveShareImageContentType(string $contentType, string $body): string
{
    if (isShareImageContentType($contentType)) {
        return normalizeShareImageContentType($contentType);
    }

    return detectShareImageContentType($body);
}

function detectShareImageContentType(string $body): string
{
    if (strncmp($body, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }

    if (strncmp($body, "\x89PNG\r\n\x1A\n", 8) === 0) {
        return 'image/png';
    }

    if (strncmp($body, 'GIF87a', 6) === 0 || strncmp($body, 'GIF89a', 6) === 0) {
        return 'image/gif';
    }

    if (substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP') {
        return 'image/webp';
    }

    return '';
}

function normalizeShareImageContentType(string $contentType): string
{
    $clean = strtolower(trim(explode(';', $contentType)[0]));

    if ($clean === 'image/jpg') {
        return 'image/jpeg';
    }

    return $clean;
}

function serveImageBytes(string $body, string $contentType): void
{
    header('Content-Type: ' . normalizeShareImageContentType($contentType));
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($body));
    echo $body;
}

function serveFallbackShareImage(): void
{
    $fallbackPath = __DIR__ . '/assets/img/logo.png';

    if (!is_file($fallbackPath) || !is_readable($fallbackPath)) {
        http_response_code(404);
        return;
    }

    serveImageBytes((string) file_get_contents($fallbackPath), 'image/png');
}
