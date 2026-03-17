<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$publicPath = __DIR__ . '/public' . $requestPath;

if ($requestPath !== '/' && is_file($publicPath)) {
    return false;
}

if ($requestPath === '/' || $requestPath === '/index.php') {
    require __DIR__ . '/public/index.php';
    return true;
}

if (str_starts_with($requestPath, '/api/')) {
    $apiScript = __DIR__ . $requestPath;

    if (is_file($apiScript)) {
        require $apiScript;
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo '404 Not Found';

return true;
