<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\JsonResponder;

$container = require __DIR__ . '/../bootstrap/app.php';

JsonResponder::send(
    $container->listNewsUseCase()->execute($container->config()->maxNewsItems())
);

