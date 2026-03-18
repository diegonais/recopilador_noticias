<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class DateHelper
{
    public static function toStorageFormat(string $dateString, string $timezone): string
    {
        $dateString = trim($dateString);

        if ($dateString === '') {
            return '';
        }

        $targetTimezone = new DateTimeZone($timezone);
        $formats = [
            DATE_RSS,
            DATE_ATOM,
            'D, d M Y H:i:s O',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $dateString);

            if ($date instanceof DateTimeImmutable) {
                return $date->setTimezone($targetTimezone)->format(DATE_ATOM);
            }
        }

        try {
            return (new DateTimeImmutable($dateString))
                ->setTimezone($targetTimezone)
                ->format(DATE_ATOM);
        } catch (Throwable) {
            return '';
        }
    }

    public static function toTimestamp(string $dateString, string $timezone): int
    {
        $normalized = self::toStorageFormat($dateString, $timezone);

        if ($normalized === '') {
            return 0;
        }

        try {
            return (new DateTimeImmutable($normalized))->getTimestamp();
        } catch (Throwable) {
            return 0;
        }
    }

    public static function nowForLog(string $timezone): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d H:i:s');
    }
}
