<?php

namespace App\Support;

class BriefcaseMeasurements
{
    public const INPUT_FIELDS = [
        'internal_length',
        'internal_width',
        'internal_height',
        'auxiliary_height',
    ];

    public const LENGTH_FIELDS = [
        'left_flap',
        'left_width',
        'sheet_length',
        'right_width',
        'second_length',
    ];

    public const WIDTH_FIELDS = [
        'top_flap',
        'width_auxiliary_height',
        'bottom_flap',
    ];

    public const COMPOSITION_FIELDS = [
        ...self::LENGTH_FIELDS,
        'top_flap',
        'height',
        'width_auxiliary_height',
        'bottom_flap',
    ];

    public static function emptyState(): array
    {
        return array_fill_keys([
            ...self::INPUT_FIELDS,
            ...self::COMPOSITION_FIELDS,
        ], null);
    }

    public static function fromDimensions(
        array $measurements,
        mixed $compensation = 5,
    ): array {
        $length = CardboardMeasurements::normalize($measurements['internal_length'] ?? null);
        $width = CardboardMeasurements::normalize($measurements['internal_width'] ?? null);
        $height = CardboardMeasurements::normalize($measurements['internal_height'] ?? null);
        $auxiliaryHeight = CardboardMeasurements::normalize($measurements['auxiliary_height'] ?? null);
        $sheetCompensation = CardboardMeasurements::normalize($compensation) ?? '5';
        $compensatedLength = CardboardMeasurements::normalize(
            (float) ($length ?? 0) + (float) $sheetCompensation,
        );
        $compensatedWidth = CardboardMeasurements::normalize(
            (float) ($width ?? 0) + (float) $sheetCompensation,
        );
        $compensatedHeight = CardboardMeasurements::normalize(
            (float) ($height ?? 0) + (float) $sheetCompensation,
        );
        $compensatedAuxiliaryHeight = CardboardMeasurements::normalize(
            (float) ($auxiliaryHeight ?? 0) + (float) $sheetCompensation,
        );
        $widthFlap = CardboardMeasurements::normalize(
            ((float) ($width ?? 0) / 2) + (float) $sheetCompensation,
        );

        return array_merge($measurements, [
            'left_flap' => CardboardMeasurements::normalize($measurements['left_flap'] ?? null),
            'left_width' => $compensatedWidth,
            'sheet_length' => $compensatedLength,
            'right_width' => $compensatedWidth,
            'second_length' => $compensatedLength,
            'top_flap' => $widthFlap,
            'height' => $compensatedHeight,
            'width_auxiliary_height' => $compensatedAuxiliaryHeight,
            'bottom_flap' => $widthFlap,
        ]);
    }

    public static function fillMissingComposition(
        array $measurements,
        mixed $compensation = 5,
    ): array {
        $calculated = self::fromDimensions($measurements, $compensation);

        foreach (self::COMPOSITION_FIELDS as $field) {
            if (array_key_exists($field, $measurements)) {
                $calculated[$field] = CardboardMeasurements::normalize($measurements[$field]);
            }
        }

        return $calculated;
    }

    public static function lengthTotal(array $measurements): float
    {
        return CardboardMeasurements::total($measurements, self::LENGTH_FIELDS);
    }

    public static function widthTotal(array $measurements): float
    {
        return CardboardMeasurements::total($measurements, self::WIDTH_FIELDS);
    }
}
