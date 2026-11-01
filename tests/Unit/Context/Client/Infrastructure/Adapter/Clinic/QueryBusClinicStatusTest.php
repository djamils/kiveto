<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Adapter\Clinic;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Client\Infrastructure\Adapter\Animal\QueryBusClientAnimalNames;
use App\Context\Client\Infrastructure\Adapter\Clinic\QueryBusClinicStatus;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

/** The adapters of the cross-clinic mobile lookup: clinic status, animal names. */
final class QueryBusClinicStatusTest extends TestCase
{
    public function testOnlyActiveClinicsAreKept(): void
    {
        $bus = new AnsweringQueryBus([GetClinic::class => static fn (GetClinic $q): ?ClinicDto => match ($q->clinicId) {
            'active'    => self::clinic('active', ClinicStatus::ACTIVE),
            'suspended' => self::clinic('suspended', ClinicStatus::SUSPENDED),
            'closed'    => self::clinic('closed', ClinicStatus::CLOSED),
            default     => null,
        }]);

        self::assertSame(['active'], new QueryBusClinicStatus($bus)->activeClinicIds(['suspended', 'active', 'closed', 'unknown']));
    }

    public function testAnimalNamesComeFromTheAnimalSummaries(): void
    {
        $bus = new AnsweringQueryBus([ListAnimalSummariesPerClientIds::class => ['client-1' => [new AnimalSummary('a1', 'Moka', 'dog'), new AnimalSummary('a2', 'Rex', 'cat')]]]);

        self::assertSame(['client-1' => ['Moka', 'Rex']], new QueryBusClientAnimalNames($bus)->namesPerClient('clinic-1', ['client-1', 'client-2']));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAnimalSummariesPerClientIds::class, $query);
        self::assertSame(['clinic-1', ['client-1', 'client-2'], 20], [$query->clinicId, $query->clientIds, $query->limit]);
    }

    private static function clinic(string $id, ClinicStatus $status): ClinicDto
    {
        return new ClinicDto($id, 'Clinique', 'clinique', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', $status, null, '2026-01-01', '2026-01-01');
    }
}
