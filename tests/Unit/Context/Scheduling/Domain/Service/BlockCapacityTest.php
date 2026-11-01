<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Service\BlockCapacity;
use PHPUnit\Framework\TestCase;

final class BlockCapacityTest extends TestCase
{
    public function testCapacityIsPerHourOfTheWindowRoundedDown(): void
    {
        self::assertSame(16, BlockCapacity::of(4, 240));
        self::assertSame(2, BlockCapacity::of(3, 50));
        self::assertSame(0, BlockCapacity::of(0, 240));
    }

    public function testABlockIsFullOnceItsCapacityIsReachedAndZeroIsUnlimited(): void
    {
        self::assertFalse(BlockCapacity::isReached(4, 240, 15));
        self::assertTrue(BlockCapacity::isReached(4, 240, 16));
        self::assertFalse(BlockCapacity::isReached(0, 240, 500));
        // Below one booking per window: unlimited as well
        self::assertFalse(BlockCapacity::isReached(1, 30, 3));
    }
}
