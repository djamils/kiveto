<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\Exception;

use App\Animal\Domain\Exception\AnimalAlreadyArchivedException;
use App\Animal\Domain\Exception\AnimalArchivedCannotBeModifiedException;
use App\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Animal\Domain\Exception\AnimalMustHavePrimaryOwnerException;
use App\Animal\Domain\Exception\AnimalNotFoundException;
use App\Animal\Domain\Exception\DuplicateActiveOwnerException;
use App\Animal\Domain\Exception\InvalidIdentificationException;
use App\Animal\Domain\Exception\InvalidLifeStatusException;
use App\Animal\Domain\Exception\InvalidTransferStatusException;
use App\Animal\Domain\Exception\MicrochipAlreadyUsedException;
use App\Animal\Domain\Exception\OwnershipNotFoundException;
use App\Animal\Domain\Exception\PrimaryOwnerConflictException;
use PHPUnit\Framework\TestCase;

final class AnimalExceptionsTest extends TestCase
{
    public function testAnimalNotFoundException(): void
    {
        $exception = AnimalNotFoundException::withId('animal-123');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Animal with ID "animal-123" not found.', $exception->getMessage());
    }

    public function testAnimalAlreadyArchivedException(): void
    {
        $exception = AnimalAlreadyArchivedException::create('animal-456');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Animal "animal-456" is already archived.', $exception->getMessage());
    }

    public function testAnimalArchivedCannotBeModifiedException(): void
    {
        $exception = AnimalArchivedCannotBeModifiedException::create('animal-789');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-789', $exception->getMessage());
        self::assertStringContainsString('cannot be modified', $exception->getMessage());
    }

    public function testAnimalClinicMismatchException(): void
    {
        $exception = AnimalClinicMismatchException::create('animal-123', 'clinic-456', 'clinic-789');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-123', $exception->getMessage());
        self::assertStringContainsString('clinic-456', $exception->getMessage());
        self::assertStringContainsString('clinic-789', $exception->getMessage());
    }

    public function testAnimalMustHavePrimaryOwnerException(): void
    {
        $exception = AnimalMustHavePrimaryOwnerException::create('animal-123');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-123', $exception->getMessage());
        self::assertStringContainsString('primary owner', $exception->getMessage());
    }

    public function testDuplicateActiveOwnerException(): void
    {
        $exception = DuplicateActiveOwnerException::create('animal-456', 'client-123');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-456', $exception->getMessage());
        self::assertStringContainsString('client-123', $exception->getMessage());
        self::assertStringContainsString('already an active owner', $exception->getMessage());
    }

    public function testInvalidIdentificationException(): void
    {
        $exception = new InvalidIdentificationException('Registry number must be null.');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Registry number must be null.', $exception->getMessage());
    }

    public function testInvalidLifeStatusException(): void
    {
        $exception = new InvalidLifeStatusException('ALIVE status requires dates to be null.');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('ALIVE status requires dates to be null.', $exception->getMessage());
    }

    public function testInvalidTransferStatusException(): void
    {
        $exception = new InvalidTransferStatusException('NONE status requires dates to be null.');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('NONE status requires dates to be null.', $exception->getMessage());
    }

    public function testMicrochipAlreadyUsedException(): void
    {
        $exception = MicrochipAlreadyUsedException::create('123456789', 'clinic-abc');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('123456789', $exception->getMessage());
        self::assertStringContainsString('clinic-abc', $exception->getMessage());
        self::assertStringContainsString('already used', $exception->getMessage());
    }

    public function testOwnershipNotFoundException(): void
    {
        $exception = OwnershipNotFoundException::create('animal-789', 'client-999');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-789', $exception->getMessage());
        self::assertStringContainsString('client-999', $exception->getMessage());
        self::assertStringContainsString('not found', $exception->getMessage());
    }

    public function testPrimaryOwnerConflictException(): void
    {
        $exception = PrimaryOwnerConflictException::create('animal-111');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('animal-111', $exception->getMessage());
        self::assertStringContainsString('multiple primary owners', $exception->getMessage());
    }
}
