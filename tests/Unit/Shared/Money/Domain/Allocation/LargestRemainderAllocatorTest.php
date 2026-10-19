<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\Allocation;

use App\Shared\Money\Domain\Allocation\LargestRemainderAllocator;
use App\Shared\Money\Domain\Exception\AllocationException;
use PHPUnit\Framework\TestCase;

final class LargestRemainderAllocatorTest extends TestCase
{
    public function testSharesAreProportionalAndAddUpExactly(): void
    {
        // 2500 over 1000 / 1000 / 1000: 833.33 each, the first takes the extra unit.
        self::assertSame([834, 833, 833], LargestRemainderAllocator::allocate(2500, [1000, 1000, 1000]));
        self::assertSame([75, 25], LargestRemainderAllocator::allocate(100, [3, 1]));
    }

    public function testLeftoverUnitsGoToTheLargestRemainders(): void
    {
        // 10 over 1 / 2 / 4 → 1.428, 2.857, 5.714: floors 1 / 2 / 5, one unit
        // left goes to the largest remainder (0.857, index 1).
        self::assertSame([1, 3, 6], LargestRemainderAllocator::allocate(10, [1, 2, 4]));
    }

    public function testTiesGoToTheFirstIndex(): void
    {
        self::assertSame([1, 1, 0], LargestRemainderAllocator::allocate(2, [1, 1, 1]));
    }

    public function testNegativeTotalsMirrorThePositiveSplit(): void
    {
        self::assertSame([-4, -3, -3], LargestRemainderAllocator::allocate(-10, [1, 1, 1]));
    }

    public function testAllZeroWeightsSplitEqually(): void
    {
        self::assertSame([4, 3, 3], LargestRemainderAllocator::allocate(10, [0, 0, 0]));
    }

    public function testZeroWeightGetsNothing(): void
    {
        self::assertSame([0, 10], LargestRemainderAllocator::allocate(10, [0, 5]));
    }

    public function testZeroTotalGivesZeroShares(): void
    {
        self::assertSame([0, 0], LargestRemainderAllocator::allocate(0, [3, 7]));
    }

    public function testLargeAmountsDoNotOverflow(): void
    {
        $shares = LargestRemainderAllocator::allocate(\PHP_INT_MAX, [\PHP_INT_MAX, \PHP_INT_MAX]);

        self::assertSame(\PHP_INT_MAX, $shares[0] + $shares[1]);
    }

    public function testEmptyWeightsAreRefused(): void
    {
        $this->expectException(AllocationException::class);

        LargestRemainderAllocator::allocate(10, []);
    }

    public function testNegativeWeightsAreRefused(): void
    {
        $this->expectException(AllocationException::class);

        LargestRemainderAllocator::allocate(10, [1, -1]);
    }
}
