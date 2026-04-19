<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Domain\Exception\InvalidClinicTimezoneStored;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalClinicTimezoneResolver implements ClinicTimezoneResolverInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function resolveTimezone(ClinicId $clinicId): \DateTimeZone
    {
        $row = $this->connection->fetchAssociative(
            'SELECT time_zone FROM clinic__clinics WHERE id = :id',
            ['id' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        if (false === $row) {
            throw new \RuntimeException(\sprintf('Clinic not found: %s', $clinicId->toString()));
        }

        $tzString = \App\Shared\Infrastructure\Persistence\RowAccessor::string($row, 'time_zone');

        try {
            return new \DateTimeZone($tzString);
        } catch (\Exception $e) {
            throw new InvalidClinicTimezoneStored(
                \sprintf('Invalid timezone stored for clinic %s: %s', $clinicId->toString(), $tzString),
                0,
                $e,
            );
        }
    }
}
