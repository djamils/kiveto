<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Command\RecordAnimalWeight;

use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeight;
use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeightHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Exception\AnimalNotFoundException;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\Repository\WeightRecordRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Context\Animal\Domain\WeightRecord;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class RecordAnimalWeightHandlerTest extends TestCase
{
    private const string RECORD_ID = '0192c000-0000-7000-8000-000000000001';
    private const string ANIMAL_ID = '0192b000-0000-7000-8000-000000000001';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testRecordsTheWeighingOfAnAnimalOfTheClinic(): void
    {
        $now = new \DateTimeImmutable('2026-08-22 10:30:00');

        $animals = $this->createMock(AnimalRepositoryInterface::class);
        $animals->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AnimalId $id): bool => self::ANIMAL_ID === $id->toString()),
            )
            ->willReturn($this->animal())
        ;

        $saved   = null;
        $records = $this->createMock(WeightRecordRepositoryInterface::class);
        $records->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (WeightRecord $record) use (&$saved): void {
                $saved = $record;
            })
        ;

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $recordId = $this->handler($animals, $records, $eventBus, $now)(new RecordAnimalWeight(
            clinicId: self::CLINIC_ID,
            animalId: self::ANIMAL_ID,
            weightGrams: 14_200,
            measuredOn: '2026-08-22',
            bodyConditionScore: 6,
            authorName: 'Camille',
        ));

        self::assertSame(self::RECORD_ID, $recordId);
        self::assertInstanceOf(WeightRecord::class, $saved);
        self::assertSame(self::RECORD_ID, $saved->id()->toString());
        self::assertSame(self::ANIMAL_ID, $saved->animalId()->toString());
        self::assertSame(self::CLINIC_ID, $saved->clinicId()->toString());
        self::assertSame(14_200, $saved->weightGrams());
        self::assertSame(6, $saved->bodyConditionScore());
        self::assertSame('2026-08-22', $saved->measuredOn()->format('Y-m-d'));
        self::assertSame('Camille', $saved->authorName());
        self::assertSame($now, $saved->recordedAt());
    }

    public function testRefusesAnAnimalThatIsNotInTheClinic(): void
    {
        $animals = $this->createStub(AnimalRepositoryInterface::class);
        $animals->method('get')->willThrowException(new AnimalNotFoundException(self::ANIMAL_ID));

        $records = $this->createMock(WeightRecordRepositoryInterface::class);
        $records->expects(self::never())->method('save');

        $this->expectException(AnimalNotFoundException::class);

        $this->handler($animals, $records, $this->createStub(EventBusInterface::class), new \DateTimeImmutable())(
            new RecordAnimalWeight(self::CLINIC_ID, self::ANIMAL_ID, 14_200, '2026-08-22', null, 'Camille'),
        );
    }

    public function testRejectsAMalformedDate(): void
    {
        $records = $this->createMock(WeightRecordRepositoryInterface::class);
        $records->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler(
            $this->knownAnimal(),
            $records,
            $this->createStub(EventBusInterface::class),
            new \DateTimeImmutable(),
        )(new RecordAnimalWeight(self::CLINIC_ID, self::ANIMAL_ID, 14_200, '22/08/2026', null, 'Camille'));
    }

    private function handler(
        AnimalRepositoryInterface $animals,
        WeightRecordRepositoryInterface $records,
        EventBusInterface $eventBus,
        \DateTimeImmutable $now,
    ): RecordAnimalWeightHandler {
        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::RECORD_ID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        return new RecordAnimalWeightHandler($animals, $records, $uuids, new DomainEventPublisher($eventBus), $clock);
    }

    private function knownAnimal(): AnimalRepositoryInterface
    {
        $animals = $this->createStub(AnimalRepositoryInterface::class);
        $animals->method('get')->willReturn($this->animal());

        return $animals;
    }

    private function animal(): Animal
    {
        return Animal::create(
            id: AnimalId::fromString(self::ANIMAL_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: 'Roxy',
            species: Species::DOG,
            sex: Sex::FEMALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: false,
            breedName: 'Beagle',
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: '01234567-89ab-cdef-0123-456789abcdef',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );
    }
}
