<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientsByMobilePhone;

use App\Context\Client\Application\Port\ClientAnimalNamesInterface;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Port\ClinicStatusInterface;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\ListClientsByMobilePhone;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\ListClientsByMobilePhoneHandler;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\MobileClientMatch;
use PHPUnit\Framework\TestCase;

final class ListClientsByMobilePhoneHandlerTest extends TestCase
{
    private const string ACTIVE    = '01960000-0000-7000-8000-00000000c001';
    private const string SUSPENDED = '01960000-0000-7000-8000-00000000c002';

    public function testKeepsTheFilesOfActiveClinicsWithTheirAnimals(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())->method('findByMobilePhone')->with('+33612345678')->willReturn([
            ['clinicId' => self::ACTIVE, 'clientId' => 'client-1', 'firstName' => 'Sophie', 'lastName' => 'Martin'],
            ['clinicId' => self::ACTIVE, 'clientId' => 'client-2', 'firstName' => 'Paul', 'lastName' => 'Martin'],
            ['clinicId' => self::SUSPENDED, 'clientId' => 'client-3', 'firstName' => 'Sophie', 'lastName' => 'Martin'],
        ]);
        $clinics = $this->createMock(ClinicStatusInterface::class);
        $clinics->expects(self::once())->method('activeClinicIds')->with([self::ACTIVE, self::SUSPENDED])->willReturn([self::ACTIVE]);
        $animals = $this->createMock(ClientAnimalNamesInterface::class);
        $animals->expects(self::once())->method('namesPerClient')->with(self::ACTIVE, ['client-1', 'client-2'])->willReturn(['client-1' => ['Moka', 'Rex']]);

        $matches = new ListClientsByMobilePhoneHandler($repository, $clinics, $animals)(new ListClientsByMobilePhone(' +33612345678 '));

        self::assertSame([
            [self::ACTIVE, 'client-1', 'Sophie', 'Martin', ['Moka', 'Rex']],
            [self::ACTIVE, 'client-2', 'Paul', 'Martin', []],
        ], array_map(static fn (MobileClientMatch $m): array => [$m->clinicId, $m->clientId, $m->firstName, $m->lastName, $m->animalNames], $matches));
    }

    public function testABlankNumberOrNoFileMatchesNothing(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())->method('findByMobilePhone')->willReturn([]);
        $clinics = $this->createMock(ClinicStatusInterface::class);
        $clinics->expects(self::never())->method('activeClinicIds');
        $handler = new ListClientsByMobilePhoneHandler($repository, $clinics, $this->createStub(ClientAnimalNamesInterface::class));

        self::assertSame([], $handler(new ListClientsByMobilePhone('  ')));
        self::assertSame([], $handler(new ListClientsByMobilePhone('+33600000000')));
    }
}
