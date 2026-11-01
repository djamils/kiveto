<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Domain\Exception\InvalidClinicTimezoneStored;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalClinicTimezoneResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The clinic's stored time zone drives every local-day computation: a missing
 * clinic and a corrupt zone are both reported, never silently replaced.
 */
final class DbalClinicTimezoneResolverTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000c0c01';

    public function testResolvesTheStoredTimezone(): void
    {
        $tz = $this->resolver(['time_zone' => 'Europe/Paris'])->resolveTimezone(ClinicId::fromString(self::CLINIC_ID));

        self::assertSame('Europe/Paris', $tz->getName());
    }

    public function testAnUnknownClinicThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic not found: ' . self::CLINIC_ID);

        $this->resolver(false)->resolveTimezone(ClinicId::fromString(self::CLINIC_ID));
    }

    public function testACorruptStoredTimezoneThrows(): void
    {
        try {
            $this->resolver(['time_zone' => 'Mars/Olympus'])->resolveTimezone(ClinicId::fromString(self::CLINIC_ID));
            self::fail('A corrupt time zone should be reported.');
        } catch (InvalidClinicTimezoneStored $e) {
            self::assertSame(\sprintf('Invalid timezone stored for clinic %s: Mars/Olympus', self::CLINIC_ID), $e->getMessage());
            self::assertInstanceOf(\Exception::class, $e->getPrevious());
        }
    }

    /**
     * @param array<string, string>|false $row
     */
    private function resolver(array|false $row): DbalClinicTimezoneResolver
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);

        return new DbalClinicTimezoneResolver($connection);
    }
}
