<?php

namespace Tests\Unit;

use App\Support\Sales\SaleCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The POS app runs the same cases (tests/js/calc.test.ts), so device and server agree. */
class SaleCalculatorTest extends TestCase
{
    public static function cases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../fixtures/sale-calc.json'), true);

        return collect($cases)->mapWithKeys(fn ($c) => [$c['name'] => [$c['input'], $c['expected']]])->all();
    }

    #[DataProvider('cases')]
    public function test_matches_fixture(array $input, array $expected): void
    {
        $this->assertEquals($expected, SaleCalculator::calculate($input));
    }
}
