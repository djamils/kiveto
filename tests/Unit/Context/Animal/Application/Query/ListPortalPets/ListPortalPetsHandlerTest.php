<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListPortalPets;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListPortalPets\ListPortalPets;
use App\Context\Animal\Application\Query\ListPortalPets\ListPortalPetsHandler;
use App\Context\Animal\Application\Query\ListPortalPets\PortalPetView;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListPortalPetsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testReturnsNothingWithoutQueryingForNoClient(): void
    {
        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::never())->method('listPortalPets');

        $handler = new ListPortalPetsHandler($repository);

        self::assertSame([], $handler(new ListPortalPets(self::CLINIC_ID, [])));
    }

    public function testDelegatesToTheReadRepository(): void
    {
        $clientIds = ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'];
        $expected  = [new PortalPetView(
            id: '11111111-1111-1111-1111-111111111111',
            primaryOwnerClientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            name: 'Rex',
            species: 'dog',
            breedName: null,
            isMixedBreed: true,
            sex: 'male',
            reproductiveStatus: 'neutered',
            birthDate: new \DateTimeImmutable('2019-04-12'),
            lifeStatus: 'alive',
            microchipNumber: '250269812345678',
        )];

        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listPortalPets')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::identicalTo($clientIds),
            )
            ->willReturn($expected)
        ;

        $handler = new ListPortalPetsHandler($repository);

        self::assertSame($expected, $handler(new ListPortalPets(self::CLINIC_ID, $clientIds)));
    }
}
