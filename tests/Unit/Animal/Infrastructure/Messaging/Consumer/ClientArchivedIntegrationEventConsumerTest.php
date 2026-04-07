<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Infrastructure\Messaging\Consumer;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Infrastructure\Messaging\Consumer\ClientArchivedIntegrationEventConsumer;
use App\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ClientArchivedIntegrationEventConsumerTest extends TestCase
{
    public function testConsumerResolvesOwnershipWhenAnimalsFound(): void
    {
        $clientId = \Symfony\Component\Uid\Uuid::v7()->toString();
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
            now: $now
        );

        $animalRepository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);
        $logger           = $this->createStub(LoggerInterface::class);

        $animalRepository->expects($this->once())
            ->method('findByActiveOwner')
            ->with($clinicId, $clientId)
            ->willReturn([$animal])
        ;

        $clock->expects($this->once())
            ->method('now')
            ->willReturn($now)
        ;

        $animalRepository->expects($this->once())
            ->method('save')
            ->with($animal)
        ;

        $eventBus->expects($this->once())
            ->method('publish')
        ;

        $consumer = new ClientArchivedIntegrationEventConsumer(
            $animalRepository,
            $eventBus,
            $clock,
            $logger
        );

        $event = new ClientArchivedIntegrationEvent(
            clinicId: $clinicId->toString(),
            clientId: $clientId
        );

        $consumer->__invoke($event);
    }

    public function testConsumerLogsWhenNoAnimalsFound(): void
    {
        $clientId = \Symfony\Component\Uid\Uuid::v7()->toString();
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animalRepository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus         = $this->createStub(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);
        $logger           = $this->createMock(LoggerInterface::class);

        $animalRepository->expects($this->once())
            ->method('findByActiveOwner')
            ->with($clinicId, $clientId)
            ->willReturn([])
        ;

        $clock->expects($this->once())
            ->method('now')
            ->willReturn($now)
        ;

        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->logicalOr(
                    $this->equalTo('Processing ClientArchivedIntegrationEvent'),
                    $this->equalTo('No animals found for archived client')
                ),
                $this->anything()
            )
        ;

        $consumer = new ClientArchivedIntegrationEventConsumer(
            $animalRepository,
            $eventBus,
            $clock,
            $logger
        );

        $event = new ClientArchivedIntegrationEvent(
            clinicId: $clinicId->toString(),
            clientId: $clientId
        );

        $consumer->__invoke($event);
    }

    public function testConsumerThrowsExceptionOnError(): void
    {
        $clientId = \Symfony\Component\Uid\Uuid::v7()->toString();
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
            now: $now
        );

        $animalRepository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus         = $this->createStub(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);
        $logger           = $this->createMock(LoggerInterface::class);

        $animalRepository->expects($this->once())
            ->method('findByActiveOwner')
            ->with($clinicId, $clientId)
            ->willReturn([$animal])
        ;

        $clock->expects($this->once())
            ->method('now')
            ->willReturn($now)
        ;

        $animalRepository->expects($this->once())
            ->method('save')
            ->with($animal)
            ->willThrowException(new \RuntimeException('Save failed'))
        ;

        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to resolve ownership for animal'),
                $this->anything()
            )
        ;

        $this->expectException(\RuntimeException::class);

        $consumer = new ClientArchivedIntegrationEventConsumer(
            $animalRepository,
            $eventBus,
            $clock,
            $logger
        );

        $event = new ClientArchivedIntegrationEvent(
            clinicId: $clinicId->toString(),
            clientId: $clientId
        );

        $consumer->__invoke($event);
    }
}
