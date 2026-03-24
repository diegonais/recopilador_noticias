<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\JsonResponder;

$container = require __DIR__ . '/../bootstrap/app.php';

$year = toOptionalInt('year', 1900, 2100);
$month = toOptionalInt('month', 1, 12);
$day = toOptionalInt('day', 1, 31);

JsonResponder::send(
    $container->listNewsUseCase()->execute(
        $container->config()->maxNewsItems(),
        $year,
        $month,
        $day,
    )
);

function toOptionalInt(string $key, int $min, int $max): ?int
{
    if (!isset($_GET[$key])) {
        return null;
    }

    $value = filter_var(
        $_GET[$key],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => $min, 'max_range' => $max]]
    );

    return is_int($value) ? $value : null;
}
