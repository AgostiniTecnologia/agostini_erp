<?php

namespace Tests\Unit;

use App\Support\CardboardMeasurements;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardboardMeasurementsTest extends TestCase
{
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
}
