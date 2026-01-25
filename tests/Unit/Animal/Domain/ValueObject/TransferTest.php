<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidTransferStatusException;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Domain\ValueObject\TransferStatus;
use PHPUnit\Framework\TestCase;

final class TransferTest extends TestCase
{
    public function testNone(): void
    {
        $transfer = Transfer::none();

        self::assertSame(TransferStatus::NONE, $transfer->transferStatus);
        self::assertNull($transfer->soldAt);
        self::assertNull($transfer->givenAt);
    }

    public function testNoneConsistency(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::NONE,
            soldAt: null,
            givenAt: null
        );

        $transfer->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testNoneWithSoldAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::NONE,
            soldAt: new \DateTimeImmutable('2024-01-01'),
            givenAt: null
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('NONE status requires soldAt and givenAt to be null.');

        $transfer->ensureConsistency();
    }

    public function testNoneWithGivenAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::NONE,
            soldAt: null,
            givenAt: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('NONE status requires soldAt and givenAt to be null.');

        $transfer->ensureConsistency();
    }

    public function testSoldConsistency(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::SOLD,
            soldAt: new \DateTimeImmutable('2024-01-01'),
            givenAt: null
        );

        $transfer->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testSoldWithoutSoldAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::SOLD,
            soldAt: null,
            givenAt: null
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('SOLD status requires soldAt to be set and givenAt to be null.');

        $transfer->ensureConsistency();
    }

    public function testSoldWithGivenAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::SOLD,
            soldAt: new \DateTimeImmutable('2024-01-01'),
            givenAt: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('SOLD status requires soldAt to be set and givenAt to be null.');

        $transfer->ensureConsistency();
    }

    public function testGivenConsistency(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::GIVEN,
            soldAt: null,
            givenAt: new \DateTimeImmutable('2024-01-01')
        );

        $transfer->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testGivenWithoutGivenAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::GIVEN,
            soldAt: null,
            givenAt: null
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('GIVEN status requires givenAt to be set and soldAt to be null.');

        $transfer->ensureConsistency();
    }

    public function testGivenWithSoldAtThrows(): void
    {
        $transfer = new Transfer(
            transferStatus: TransferStatus::GIVEN,
            soldAt: new \DateTimeImmutable('2024-01-01'),
            givenAt: new \DateTimeImmutable('2024-01-01')
        );

        $this->expectException(InvalidTransferStatusException::class);
        $this->expectExceptionMessage('GIVEN status requires givenAt to be set and soldAt to be null.');

        $transfer->ensureConsistency();
    }
}
