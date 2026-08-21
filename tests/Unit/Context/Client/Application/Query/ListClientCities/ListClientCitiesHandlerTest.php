<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientCities;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientCities\ListClientCities;
use App\Context\Client\Application\Query\ListClientCities\ListClientCitiesHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientCitiesHandlerTest extends TestCase
{
    private const string CLINIC_ID = '11111111-1111-4111-8111-111111111111';

    public function testTheRepositoryIsAskedForTheClinicOfTheQuery(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('listCities')
            ->with(self::callback(
                static fn (ClinicId $clinicId): bool => self::CLINIC_ID === $clinicId->toString(),
            ))
            ->willReturn([])
        ;

        $handler = new ListClientCitiesHandler($repository);

        self::assertSame([], $handler(new ListClientCities(self::CLINIC_ID)));
    }

    public function testTheCitiesAreReturnedVerbatim(): void
    {
        $cities = ['Bordeaux', 'Lyon', 'Paris'];

        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('listCities')
            ->willReturn($cities)
        ;

        $handler = new ListClientCitiesHandler($repository);

        self::assertSame($cities, $handler(new ListClientCities(self::CLINIC_ID)));
    }
}
