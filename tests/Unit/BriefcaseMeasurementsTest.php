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
}
