<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\ValueObject\Ownership;
use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
use PHPUnit\Framework\TestCase;

final class OwnershipTest extends TestCase
{
    public function testIsActive(): void
    {
        $ownership = new Ownership(
            clientId: 'client-123',
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null
        );

        self::assertTrue($ownership->isActive());
    }

    public function testIsNotActive(): void
    {
        $ownership = new Ownership(
            clientId: 'client-123',
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ENDED,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: new \DateTimeImmutable('2024-06-01')
        );

        self::assertFalse($ownership->isActive());
    }

    public function testIsPrimary(): void
    {
        $ownership = new Ownership(
            clientId: 'client-123',
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null
        );

        self::assertTrue($ownership->isPrimary());
        self::assertFalse($ownership->isSecondary());
    }

    public function testIsSecondary(): void
    {
        $ownership = new Ownership(
            clientId: 'client-456',
            role: OwnershipRole::SECONDARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null
        );

        self::assertTrue($ownership->isSecondary());
        self::assertFalse($ownership->isPrimary());
    }

    public function testEnd(): void
    {
        $ownership = new Ownership(
            clientId: 'client-123',
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null
        );

        $endedAt = new \DateTimeImmutable('2024-06-01');
        $ended   = $ownership->end($endedAt);

        self::assertSame('client-123', $ended->clientId);
        self::assertSame(OwnershipRole::PRIMARY, $ended->role);
        self::assertSame(OwnershipStatus::ENDED, $ended->status);
        self::assertSame($endedAt, $ended->endedAt);
        self::assertFalse($ended->isActive());
    }

    public function testPromoteToPrimary(): void
    {
        $ownership = new Ownership(
            clientId: 'client-456',
            role: OwnershipRole::SECONDARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null
        );

        $promoted = $ownership->promoteToPrimary();

        self::assertSame('client-456', $promoted->clientId);
        self::assertSame(OwnershipRole::PRIMARY, $promoted->role);
        self::assertSame(OwnershipStatus::ACTIVE, $promoted->status);
        self::assertTrue($promoted->isPrimary());
        self::assertFalse($promoted->isSecondary());
    }

    public function testPromoteToPrimaryPreservesEndedAt(): void
    {
        $endedAt   = new \DateTimeImmutable('2024-06-01');
        $ownership = new Ownership(
            clientId: 'client-456',
            role: OwnershipRole::SECONDARY,
            status: OwnershipStatus::ENDED,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: $endedAt
        );

        $promoted = $ownership->promoteToPrimary();

        self::assertSame($endedAt, $promoted->endedAt);
        self::assertSame(OwnershipStatus::ENDED, $promoted->status);
    }
}
