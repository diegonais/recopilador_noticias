<?php

class DateHelper
{
    public static function toStorageFormat($dateString)
    {
        $dateString = trim((string) $dateString);

        if ($dateString === '') {
            return '';
        }

        $timezone = new DateTimeZone(TIMEZONE);
        $formats = array(
            DATE_RSS,
            DATE_ATOM,
            'D, d M Y H:i:s O',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
        );

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $dateString);

            if ($date instanceof DateTimeImmutable) {
                return $date->setTimezone($timezone)->format(DATE_ATOM);
            }
        }

        try {
            $date = new DateTimeImmutable($dateString);

            return $date->setTimezone($timezone)->format(DATE_ATOM);
        } catch (Throwable $exception) {
            return '';
        }
    }

    public static function formatForDisplay($dateString)
    {
        $normalized = self::toStorageFormat($dateString);

        if ($normalized === '') {
            return 'Fecha no disponible';
        }

        try {
            $date = new DateTimeImmutable($normalized);
            $months = array(
                1 => 'ene',
                2 => 'feb',
                3 => 'mar',
                4 => 'abr',
                5 => 'may',
                6 => 'jun',
                7 => 'jul',
                8 => 'ago',
                9 => 'sep',
                10 => 'oct',
                11 => 'nov',
                12 => 'dic',
            );

            $monthNumber = (int) $date->format('n');
            $month = isset($months[$monthNumber]) ? $months[$monthNumber] : $date->format('m');

            return $date->format('d') . ' ' . $month . ' ' . $date->format('Y, H:i');
        } catch (Throwable $exception) {
            return 'Fecha no disponible';
        }
    }

    public static function nowForLog()
    {
        return (new DateTimeImmutable('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d H:i:s');
    }

    public static function toTimestamp($dateString)
    {
        $normalized = self::toStorageFormat($dateString);

        if ($normalized === '') {
            return 0;
        }

        try {
            return (new DateTimeImmutable($normalized))->getTimestamp();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}