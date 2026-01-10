<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Query\ListClinicGroups;

use App\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use App\Clinic\Application\Query\ListClinicGroups\ClinicGroupsCollection;
use App\Clinic\Application\Query\ListClinicGroups\ListClinicGroups;
use App\Clinic\Application\Query\ListClinicGroups\ListClinicGroupsHandler;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use PHPUnit\Framework\TestCase;

final class ListClinicGroupsHandlerTest extends TestCase
{
    public function testReturnsClinicGroupsCollection(): void
    {
        $collection = new ClinicGroupsCollection([], 0);

        $repo = $this->createMock(ClinicGroupReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAllFiltered')
            ->with(ClinicGroupStatus::ACTIVE)
            ->willReturn($collection)
        ;

        $handler = new ListClinicGroupsHandler($repo);
        $result = $handler(new ListClinicGroups(status: ClinicGroupStatus::ACTIVE));

        self::assertSame($collection, $result);
    }

    public function testReturnsAllGroupsWithoutFilter(): void
    {
        $collection = new ClinicGroupsCollection([], 0);

        $repo = $this->createMock(ClinicGroupReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAllFiltered')
            ->with(null)
            ->willReturn($collection)
        ;

        $handler = new ListClinicGroupsHandler($repo);
        $result = $handler(new ListClinicGroups(status: null));

        self::assertSame($collection, $result);
    }
}
