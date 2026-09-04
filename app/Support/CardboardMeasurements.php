<?php

namespace App\Support;

use InvalidArgumentException;

class CardboardMeasurements
{
    public const INTERNAL_FIELDS = [
        'internal_length',
        'internal_width',
        'internal_height',
    ];

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

    public static function emptyState(): array
    {
        return array_fill_keys([
            ...self::INTERNAL_FIELDS,
            ...self::LENGTH_FIELDS,
            ...self::WIDTH_FIELDS,
        ], null);
    }

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

    /**
     * Monta as medidas de corte a partir das únicas medidas informadas pelo usuário.
     */
    public static function fromInternalDimensions(
        array $measurements,
        mixed $foldMargin = 5,
        mixed $lengthFlapDefault = 60,
    ): array {
        $length = (float) (self::normalize($measurements['internal_length'] ?? null) ?? 0);
        $width = (float) (self::normalize($measurements['internal_width'] ?? null) ?? 0);
        $height = (float) (self::normalize($measurements['internal_height'] ?? null) ?? 0);
        $margin = (float) (self::normalize($foldMargin) ?? 5);
        $lengthFlap = (float) (self::normalize($lengthFlapDefault) ?? 60);

        return array_merge($measurements, [
            'left_flap' => self::normalizedNumber($lengthFlap),
            'left_height' => self::normalizedNumber($height),
            'sheet_length' => self::normalizedNumber($length + $margin),
            'right_height' => self::normalizedNumber($height),
            'right_flap' => self::normalizedNumber($lengthFlap),
            'top_flap' => self::normalizedNumber(($width / 2) + $margin),
            'top_height' => self::normalizedNumber($height + $margin),
            'sheet_width' => self::normalizedNumber($width + $margin),
            'bottom_height' => self::normalizedNumber($height + $margin),
            'bottom_flap' => self::normalizedNumber(($width / 2) + $margin),
        ]);
    }

    private static function normalizedNumber(float $value): string
    {
        return self::normalize($value) ?? '0';
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
