<?php

namespace Tests\Unit;

use App\Support\InventoryMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InventoryMathTest extends TestCase
{
    public function test_normalize_preserves_decimal_scale(): void
    {
        $this->assertSame('10.000000', InventoryMath::normalize('10'));
        $this->assertSame('10.500000', InventoryMath::normalize('10.5'));
    }

    public function test_add_and_subtract(): void
    {
        $this->assertSame('3.000000', InventoryMath::add('1', '2'));
        $this->assertSame('0.000000', InventoryMath::sub('5', '5'));
        $this->assertSame('2.500000', InventoryMath::sub('10.5', '8'));
    }

    public function test_multiply(): void
    {
        $this->assertSame('6.000000', InventoryMath::multiply('2', '3'));
        $this->assertSame('25.500000', InventoryMath::multiply('10', '2.55'));
    }

    public function test_compare(): void
    {
        $this->assertSame(0, InventoryMath::compare('5.000000', '5'));
        $this->assertSame(1, InventoryMath::compare('6', '5.999999'));
        $this->assertSame(-1, InventoryMath::compare('4', '4.000001'));
    }

    public function test_invalid_quantity_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryMath::normalize('not-a-number');
    }

    public function test_closing_variance_formula(): void
    {
        $expected = InventoryMath::normalize('100');
        $actual = InventoryMath::normalize('97.25');
        $variance = InventoryMath::sub($actual, $expected);

        $this->assertSame('-2.750000', $variance);
    }
}
