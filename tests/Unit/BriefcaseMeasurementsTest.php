<?php

namespace Tests\Unit;

use App\Support\BriefcaseMeasurements;
use PHPUnit\Framework\TestCase;

class BriefcaseMeasurementsTest extends TestCase
{
    public function test_it_calculates_the_briefcase_composition_and_totals(): void
    {
        $measurements = BriefcaseMeasurements::fromDimensions([
            'internal_length' => '100',
            'internal_width' => '40',
            'internal_height' => '20',
            'auxiliary_height' => '30',
            'left_flap' => '10',
        ], 5);

        $this->assertSame('10', $measurements['left_flap']);
        $this->assertSame('45', $measurements['left_width']);
        $this->assertSame('105', $measurements['sheet_length']);
        $this->assertSame('45', $measurements['right_width']);
        $this->assertSame('105', $measurements['second_length']);
        $this->assertSame('25', $measurements['top_flap']);
        $this->assertSame('25', $measurements['height']);
        $this->assertSame('35', $measurements['width_auxiliary_height']);
        $this->assertSame('25', $measurements['bottom_flap']);
        $this->assertSame(310.0, BriefcaseMeasurements::lengthTotal($measurements));
        $this->assertSame(85.0, BriefcaseMeasurements::widthTotal($measurements));
    }

    public function test_manual_composition_adjustments_are_preserved(): void
    {
        $measurements = BriefcaseMeasurements::fillMissingComposition([
            'internal_length' => '100',
            'internal_width' => '40',
            'internal_height' => '20',
            'auxiliary_height' => '30',
            'left_flap' => '70',
            'top_flap' => '30',
            'width_auxiliary_height' => '35',
        ], 5);

        $this->assertSame('70', $measurements['left_flap']);
        $this->assertSame('30', $measurements['top_flap']);
        $this->assertSame('35', $measurements['width_auxiliary_height']);
    }

    public function test_it_uses_the_height_in_the_width_total_when_auxiliary_height_is_missing(): void
    {
        $measurements = BriefcaseMeasurements::fromDimensions([
            'internal_length' => '100',
            'internal_width' => '40',
            'internal_height' => '20',
            'auxiliary_height' => null,
        ], 5);

        $this->assertSame('25', $measurements['height']);
        $this->assertSame('0', $measurements['width_auxiliary_height']);
        $this->assertSame(75.0, BriefcaseMeasurements::widthTotal($measurements));
    }

    public function test_it_does_not_add_compensation_to_zero_or_missing_measurements(): void
    {
        $measurements = BriefcaseMeasurements::fromDimensions([
            'internal_length' => null,
            'internal_width' => null,
            'internal_height' => null,
            'auxiliary_height' => '0',
        ], 10);

        $this->assertSame('0', $measurements['sheet_length']);
        $this->assertSame('0', $measurements['left_width']);
        $this->assertSame('0', $measurements['right_width']);
        $this->assertSame('0', $measurements['second_length']);
        $this->assertSame('0', $measurements['top_flap']);
        $this->assertSame('0', $measurements['height']);
        $this->assertSame('0', $measurements['width_auxiliary_height']);
        $this->assertSame('0', $measurements['bottom_flap']);
    }

    public function test_zero_auxiliary_height_uses_the_main_height_in_the_width_total(): void
    {
        $measurements = BriefcaseMeasurements::fromDimensions([
            'internal_length' => '100',
            'internal_width' => '50',
            'internal_height' => '150',
            'auxiliary_height' => '0',
        ], 10);

        $this->assertSame('160', $measurements['height']);
        $this->assertSame('0', $measurements['width_auxiliary_height']);
        $this->assertSame(230.0, BriefcaseMeasurements::widthTotal($measurements));
    }
}
