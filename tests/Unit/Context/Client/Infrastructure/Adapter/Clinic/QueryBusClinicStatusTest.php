<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Adapter\Clinic;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Client\Infrastructure\Adapter\Animal\QueryBusClientAnimalNames;
use App\Context\Client\Infrastructure\Adapter\Clinic\QueryBusClinicStatus;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\ClinicGroupDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\GetClinicGroup;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupStatus;
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

    /** S7-04: a clinic of a suspended group is inactive, as in the directory; each group is asked once. */
    public function testAClinicOfASuspendedGroupIsNotKept(): void
    {
        $bus = new AnsweringQueryBus([
            GetClinic::class => static fn (GetClinic $q): ClinicDto => match ($q->clinicId) {
                'in-active-group', 'other-in-active-group' => self::clinic($q->clinicId, ClinicStatus::ACTIVE, 'group-active'),
                'in-suspended-group' => self::clinic($q->clinicId, ClinicStatus::ACTIVE, 'group-suspended'),
                'in-unknown-group'   => self::clinic($q->clinicId, ClinicStatus::ACTIVE, 'group-unknown'),
                default              => self::clinic($q->clinicId, ClinicStatus::ACTIVE),
            },
            GetClinicGroup::class => static fn (GetClinicGroup $q): ?ClinicGroupDto => match ($q->clinicGroupId) {
                'group-active'    => new ClinicGroupDto('group-active', 'Réseau', ClinicGroupStatus::ACTIVE, '2026-01-01'),
                'group-suspended' => new ClinicGroupDto('group-suspended', 'Réseau', ClinicGroupStatus::SUSPENDED, '2026-01-01'),
                default           => null,
            },
        ]);

        self::assertSame(
            ['in-active-group', 'other-in-active-group', 'in-unknown-group', 'alone'],
            new QueryBusClinicStatus($bus)->activeClinicIds(['in-active-group', 'in-suspended-group', 'other-in-active-group', 'in-unknown-group', 'alone']),
        );
        self::assertCount(3, array_filter($bus->asked, static fn (object $q): bool => $q instanceof GetClinicGroup), 'once per group');
    }

    public function testAnimalNamesComeFromTheAnimalSummaries(): void
    {
        $bus = new AnsweringQueryBus([ListAnimalSummariesPerClientIds::class => ['client-1' => [new AnimalSummary('a1', 'Moka', 'dog'), new AnimalSummary('a2', 'Rex', 'cat')]]]);

        self::assertSame(['client-1' => ['Moka', 'Rex']], new QueryBusClientAnimalNames($bus)->namesPerClient('clinic-1', ['client-1', 'client-2']));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAnimalSummariesPerClientIds::class, $query);
        self::assertSame(['clinic-1', ['client-1', 'client-2'], 20], [$query->clinicId, $query->clientIds, $query->limit]);
    }

    private static function clinic(string $id, ClinicStatus $status, ?string $groupId = null): ClinicDto
    {
        return new ClinicDto($id, 'Clinique', 'clinique', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', $status, $groupId, '2026-01-01', '2026-01-01');
    }
}
