<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidLifeStatusException;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\LifeStatus;
use PHPUnit\Framework\TestCase;

final class LifeCycleTest extends TestCase
{
    public function testAlive(): void
    {
        $lifeCycle = LifeCycle::alive();

        self::assertSame(LifeStatus::ALIVE, $lifeCycle->lifeStatus);
        self::assertNull($lifeCycle->deceasedAt);
        self::assertNull($lifeCycle->missingSince);
    }

    public function testAliveConsistency(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::ALIVE,
            deceasedAt: null,
            missingSince: null
        );

        $lifeCycle->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testAliveWithDeceasedAtThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::ALIVE,
            deceasedAt: new \DateTimeImmutable('2024-01-01'),
            missingSince: null
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('ALIVE status requires deceasedAt and missingSince to be null.');

        $lifeCycle->ensureConsistency();
    }

    public function testAliveWithMissingSinceThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::ALIVE,
            deceasedAt: null,
            missingSince: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('ALIVE status requires deceasedAt and missingSince to be null.');

        $lifeCycle->ensureConsistency();
    }

    public function testDeceasedConsistency(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::DECEASED,
            deceasedAt: new \DateTimeImmutable('2024-01-01'),
            missingSince: null
        );

        $lifeCycle->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testDeceasedWithoutDeceasedAtThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::DECEASED,
            deceasedAt: null,
            missingSince: null
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('DECEASED status requires deceasedAt to be set and missingSince to be null.');

        $lifeCycle->ensureConsistency();
    }

    public function testDeceasedWithMissingSinceThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::DECEASED,
            deceasedAt: new \DateTimeImmutable('2024-01-01'),
            missingSince: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('DECEASED status requires deceasedAt to be set and missingSince to be null.');

        $lifeCycle->ensureConsistency();
    }

    public function testMissingConsistency(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::MISSING,
            deceasedAt: null,
            missingSince: new \DateTimeImmutable('2024-01-01')
        );

        $lifeCycle->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testMissingWithoutMissingSinceThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::MISSING,
            deceasedAt: null,
            missingSince: null
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('MISSING status requires missingSince to be set and deceasedAt to be null.');

        $lifeCycle->ensureConsistency();
    }

    public function testMissingWithDeceasedAtThrows(): void
    {
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::MISSING,
            deceasedAt: new \DateTimeImmutable('2024-01-01'),
            missingSince: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidLifeStatusException::class);
        $this->expectExceptionMessage('MISSING status requires missingSince to be set and deceasedAt to be null.');

        $lifeCycle->ensureConsistency();
    }
}
