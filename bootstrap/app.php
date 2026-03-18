<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Infrastructure\Container\ApplicationContainer;

$config = AppConfig::fromBasePath(dirname(__DIR__));

return new ApplicationContainer($config);
