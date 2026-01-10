<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Query\ListClinics;

use App\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Clinic\Application\Query\ListClinics\ClinicsCollection;
use App\Clinic\Application\Query\ListClinics\ListClinics;
use App\Clinic\Application\Query\ListClinics\ListClinicsHandler;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use PHPUnit\Framework\TestCase;

final class ListClinicsHandlerTest extends TestCase
{
    public function testReturnsClinicsCollection(): void
    {
        $collection = new ClinicsCollection([], 0);

        $repo = $this->createMock(ClinicReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAllFiltered')
            ->with(ClinicStatus::ACTIVE, 'group-123', 'search-term')
            ->willReturn($collection)
        ;

        $handler = new ListClinicsHandler($repo);
        $result  = $handler(new ListClinics(
            status: ClinicStatus::ACTIVE,
            clinicGroupId: 'group-123',
            search: 'search-term',
        ));

        self::assertSame($collection, $result);
    }

    public function testReturnsAllClinicsWithoutFilters(): void
    {
        $collection = new ClinicsCollection([], 0);

        $repo = $this->createMock(ClinicReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAllFiltered')
            ->with(null, null, null)
            ->willReturn($collection)
        ;

        $handler = new ListClinicsHandler($repo);
        $result  = $handler(new ListClinics(
            status: null,
            clinicGroupId: null,
            search: null,
        ));

        self::assertSame($collection, $result);
    }
}
