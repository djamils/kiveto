<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient\Application\Query\ListPatientAnimalLinks;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Context\Patient\Application\Query\ListPatientAnimalLinks\ListPatientAnimalLinks;
use App\Context\Patient\Application\Query\ListPatientAnimalLinks\ListPatientAnimalLinksHandler;
use PHPUnit\Framework\TestCase;

final class ListPatientAnimalLinksHandlerTest extends TestCase
{
    private const string CLINIC = '01950000-0000-7000-0000-0000000000c1';
    private const string LUNA   = '01950000-0000-7000-0000-0000000000d1';
    private const string REX    = '01950000-0000-7000-0000-0000000000d2';

    public function testThePatientsAreReadOnceEach(): void
    {
        $links = [self::LUNA => new PatientAnimalLinkDto(self::LUNA, null, 'Luna', null, null)];

        $repository = $this->createMock(PatientReadRepositoryInterface::class);
        $repository->expects(self::once())->method('findAnimalLinks')->with(self::CLINIC, [self::LUNA, self::REX])->willReturn($links);

        self::assertSame($links, new ListPatientAnimalLinksHandler($repository)(new ListPatientAnimalLinks(self::CLINIC, [self::LUNA, self::REX, self::LUNA])));
    }

    public function testNoPatientReadsNothing(): void
    {
        $repository = $this->createMock(PatientReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findAnimalLinks');

        self::assertSame([], new ListPatientAnimalLinksHandler($repository)(new ListPatientAnimalLinks(self::CLINIC, [])));
    }
}
