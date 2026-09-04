<?php

namespace Tests\Unit;

use App\Support\CardboardMeasurements;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardboardMeasurementsTest extends TestCase
{
    public function test_empty_state_preserves_the_reactive_measurement_structure(): void
    {
        $measurements = CardboardMeasurements::emptyState();

        $this->assertSame(
            [
                ...CardboardMeasurements::INTERNAL_FIELDS,
                ...CardboardMeasurements::LENGTH_FIELDS,
                ...CardboardMeasurements::WIDTH_FIELDS,
            ],
            array_keys($measurements),
        );
        $this->assertSame([], array_filter($measurements, fn (mixed $value): bool => $value !== null));

        $recalculated = CardboardMeasurements::fromInternalDimensions(array_merge($measurements, [
            'internal_length' => '100',
            'internal_width' => '40',
            'internal_height' => '20',
        ]), 8, 70);

        $this->assertSame('70', $recalculated['left_flap']);
        $this->assertSame('108', $recalculated['sheet_length']);
        $this->assertSame('28', $recalculated['top_flap']);
    }

    #[DataProvider('validMeasurements')]
    public function test_it_normalizes_valid_measurements(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, CardboardMeasurements::normalize($input));
    }

    public static function validMeasurements(): array
    {
        return [
            'empty' => ['', null],
            'integer' => ['1747', '1747'],
            'brazilian decimal' => ['202,5', '202.5'],
            'decimal point' => ['202.5', '202.5'],
            'brazilian thousands' => ['1.234,567', '1234.567'],
            'zero' => [0, '0'],
        ];
    }

    #[DataProvider('invalidMeasurements')]
    public function test_it_rejects_invalid_measurements(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        CardboardMeasurements::normalize($input);
    }

    public static function invalidMeasurements(): array
    {
        return [
            'negative' => ['-1'],
            'text' => ['inválido'],
            'multiple separators' => ['1,2,3'],
        ];
    }

    public function test_it_calculates_the_sheet_size(): void
    {
        $measurements = [
            'left_flap' => '60',
            'left_height' => '102',
            'sheet_length' => '1747',
            'right_height' => '102',
            'right_flap' => '60',
            'top_flap' => '202,5',
            'top_height' => '107',
            'sheet_width' => '400',
            'bottom_height' => '107',
            'bottom_flap' => '202,5',
        ];

        $this->assertSame(2071.0, CardboardMeasurements::lengthTotal($measurements));
        $this->assertSame(1019.0, CardboardMeasurements::widthTotal($measurements));
        $this->assertSame('2071', CardboardMeasurements::format(2071));
        $this->assertSame('1019', CardboardMeasurements::format(1019));
    }

    public function test_it_generates_cut_measurements_from_internal_dimensions_and_company_defaults(): void
    {
        $measurements = CardboardMeasurements::fromInternalDimensions([
            'internal_length' => '1742',
            'internal_width' => '395',
            'internal_height' => '97',
        ], 5, 60);

        $this->assertSame('60', $measurements['left_flap']);
        $this->assertSame('97', $measurements['left_height']);
        $this->assertSame('1747', $measurements['sheet_length']);
        $this->assertSame('202.5', $measurements['top_flap']);
        $this->assertSame('102', $measurements['top_height']);
        $this->assertSame('400', $measurements['sheet_width']);
        $this->assertSame(2061.0, CardboardMeasurements::lengthTotal($measurements));
        $this->assertSame(1009.0, CardboardMeasurements::widthTotal($measurements));
    }
}
