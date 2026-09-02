<?php

namespace App\Support;

use InvalidArgumentException;

class CardboardMeasurements
{
    public const LENGTH_FIELDS = [
        'left_flap',
        'left_height',
        'sheet_length',
        'right_height',
        'right_flap',
    ];

    public const WIDTH_FIELDS = [
        'top_flap',
        'top_height',
        'sheet_width',
        'bottom_height',
        'bottom_flap',
    ];

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(' ', '', trim((string) $value));

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
        }

        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized) || (float) $normalized < 0) {
            throw new InvalidArgumentException('A medida deve ser um número maior ou igual a zero.');
        }

        return rtrim(rtrim(number_format((float) $normalized, 3, '.', ''), '0'), '.');
    }

    public static function total(array $measurements, array $fields): float
    {
        return array_reduce(
            $fields,
            fn (float $total, string $field): float => $total + (float) (self::normalize($measurements[$field] ?? null) ?? 0),
            0.0,
        );
    }

    public static function lengthTotal(array $measurements): float
    {
        return self::total($measurements, self::LENGTH_FIELDS);
    }

    public static function widthTotal(array $measurements): float
    {
        return self::total($measurements, self::WIDTH_FIELDS);
    }

    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
    }
}
