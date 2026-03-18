<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $className) use ($basePath): void {
    $prefix = 'PortalNoticias\\';

    if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $fullPath = $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($fullPath)) {
        require_once $fullPath;
    }
});
