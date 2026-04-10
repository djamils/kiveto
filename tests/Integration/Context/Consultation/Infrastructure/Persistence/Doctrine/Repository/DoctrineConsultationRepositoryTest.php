<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AnimalId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\OwnerId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Domain\ValueObject\WaitingRoomEntryId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineConsultationRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ENTRY_ID        = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string OWNER_ID        = '66666666-6666-4666-8666-666666666666';
    private const string ANIMAL_ID       = '77777777-7777-4777-8777-777777777777';

    private ConsultationRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repo instanceof ConsultationRepositoryInterface);
        $this->repository = $repo;
    }

    public function testFindByIdReturnsNullWhenConsultationDoesNotExist(): void
    {
        $result = $this->repository->findById(
            ConsultationId::fromString('00000000-0000-4000-8000-000000000000'),
        );

        self::assertNull($result);
    }

    public function testSaveAndFindRoundTripFromAppointment(): void
    {
        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            OwnerId::fromString(self::OWNER_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);
        self::assertSame(self::CONSULTATION_ID, $loaded->getId()->toString());
        self::assertSame(self::APPOINTMENT_ID, $loaded->getAppointmentId()?->toString());
        self::assertNull($loaded->getWaitingRoomEntryId());
        self::assertSame(self::OWNER_ID, $loaded->getOwnerId()?->toString());
        self::assertSame(self::ANIMAL_ID, $loaded->getAnimalId()?->toString());
        self::assertSame(ConsultationStatus::OPEN, $loaded->getStatus());
    }

    public function testSaveAndFindRoundTripFromWaitingRoomEntry(): void
    {
        $consultation = Consultation::startFromWaitingRoomEntry(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);
        self::assertNull($loaded->getAppointmentId());
        self::assertSame(self::ENTRY_ID, $loaded->getWaitingRoomEntryId()?->toString());
    }

    public function testRoundTripWithFullLifecycle(): void
    {
        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            OwnerId::fromString(self::OWNER_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
        $this->repository->save($consultation);

        // Reload, drive the lifecycle, save again — exercises updateEntity branches.
        $reloaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($reloaded);

        $reloaded->recordChiefComplaint('Limping', new \DateTimeImmutable('2026-04-10 09:05:00'));
        $reloaded->recordVitals(
            Vitals::create(12.5, 38.2),
            new \DateTimeImmutable('2026-04-10 09:10:00'),
        );
        $reloaded->addClinicalNote(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:15:00'),
        );
        $reloaded->addPerformedAct(
            'Otoscopy',
            1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
        );
        $reloaded->close(
            UserId::fromString(self::USER_ID),
            'Stable',
            new \DateTimeImmutable('2026-04-10 09:30:00'),
        );

        $this->repository->save($reloaded);

        // Reload again to verify everything round-tripped.
        $final = $this->repository->findById($consultation->getId());
        self::assertNotNull($final);
        self::assertSame(ConsultationStatus::CLOSED, $final->getStatus());
        self::assertSame('Limping', $final->getChiefComplaint());
        self::assertSame('Stable', $final->getSummary());
        self::assertNotNull($final->getVitals());
        self::assertSame(12.5, $final->getVitals()->getWeightKg());
        self::assertSame(38.2, $final->getVitals()->getTemperatureC());
        self::assertCount(1, $final->getNotes());
        self::assertCount(1, $final->getActs());

        // Sanity-check the entity-level getConsultationId() getters by querying
        // the children directly via the EntityManager.
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof \Doctrine\ORM\EntityManagerInterface);
        $consultationIdBinary = \Symfony\Component\Uid\Uuid::fromString(self::CONSULTATION_ID)->toBinary();

        $noteEntity = $em->getRepository(
            \App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity::class,
        )->findOneBy(['consultationId' => $consultationIdBinary]);
        self::assertNotNull($noteEntity);
        self::assertSame($consultationIdBinary, $noteEntity->getConsultationId());

        $actEntity = $em->getRepository(
            \App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity::class,
        )->findOneBy(['consultationId' => $consultationIdBinary]);
        self::assertNotNull($actEntity);
        self::assertSame($consultationIdBinary, $actEntity->getConsultationId());
    }
}
