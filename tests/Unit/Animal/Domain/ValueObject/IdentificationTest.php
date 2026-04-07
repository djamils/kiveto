<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidIdentificationException;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\RegistryType;
use PHPUnit\Framework\TestCase;

final class IdentificationTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $identification = Identification::createEmpty();

        self::assertNull($identification->microchipNumber);
        self::assertNull($identification->tattooNumber);
        self::assertNull($identification->passportNumber);
        self::assertSame(RegistryType::NONE, $identification->registryType);
        self::assertNull($identification->registryNumber);
        self::assertNull($identification->sireNumber);
    }

    public function testWithMicrochip(): void
    {
        $identification = Identification::createEmpty()
            ->withMicrochip('123456789012345')
        ;

        self::assertSame('123456789012345', $identification->microchipNumber);
        self::assertNull($identification->tattooNumber);
    }

    public function testWithMicrochipNull(): void
    {
        $identification = Identification::createEmpty()
            ->withMicrochip('123456789012345')
            ->withMicrochip(null)
        ;

        self::assertNull($identification->microchipNumber);
    }

    public function testEnsureConsistencyPassesWhenValid(): void
    {
        $identification = new Identification(
            microchipNumber: '123456789012345',
            tattooNumber: 'ABC123',
            passportNumber: 'PASS123',
            registryType: RegistryType::LOF,
            registryNumber: 'LOF123',
            sireNumber: 'SIRE123'
        );

        $identification->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testEnsureConsistencyThrowsWhenRegistryTypeNoneWithNumber(): void
    {
        $identification = new Identification(
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: RegistryType::NONE,
            registryNumber: 'SHOULD_BE_NULL',
            sireNumber: null
        );

        $this->expectException(InvalidIdentificationException::class);
        $this->expectExceptionMessage('RegistryNumber must be null when RegistryType is NONE.');

        $identification->ensureConsistency();
    }

    public function testEnsureConsistencyPassesWhenRegistryTypeNoneWithNullNumber(): void
    {
        $identification = new Identification(
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: RegistryType::NONE,
            registryNumber: null,
            sireNumber: null
        );

        $identification->ensureConsistency();

        $this->addToAssertionCount(1); // No exception thrown
    }
}
