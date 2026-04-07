<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query\GetAnimalById;

use App\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Animal\Application\Query\GetAnimalById\GetAnimalByIdHandler;
use App\Animal\Domain\Exception\AnimalNotFoundException;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class GetAnimalByIdHandlerTest extends TestCase
{
    public function testHandleReturnsView(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');

        $view = new AnimalView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: '2020-01-01',
            color: 'Golden',
            photoUrl: 'https://example.com/photo.jpg',
            identification: new \App\Animal\Application\Query\GetAnimalById\IdentificationDto(
                microchipNumber: '123456789',
                tattooNumber: null,
                passportNumber: null,
                registryType: 'lof',
                registryNumber: 'LOF123',
                sireNumber: null
            ),
            lifeCycle: new \App\Animal\Application\Query\GetAnimalById\LifeCycleDto(
                lifeStatus: 'alive',
                deceasedAt: null,
                missingSince: null
            ),
            transfer: new \App\Animal\Application\Query\GetAnimalById\TransferDto(
                transferStatus: 'none',
                soldAt: null,
                givenAt: null
            ),
            auxiliaryContact: null,
            status: 'ACTIVE',
            ownerships: [],
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        $query = new GetAnimalById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);

        $readRepository->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (AnimalId $id) => $id->equals($animalId))
            )
            ->willReturn($view)
        ;

        $handler = new GetAnimalByIdHandler($readRepository);
        $result  = $handler($query);

        self::assertSame($view, $result);
    }

    public function testHandleThrowsExceptionWhenNotFound(): void
    {
        $query = new GetAnimalById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);

        $readRepository->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $this->expectException(AnimalNotFoundException::class);

        $handler = new GetAnimalByIdHandler($readRepository);
        $handler($query);
    }
}
