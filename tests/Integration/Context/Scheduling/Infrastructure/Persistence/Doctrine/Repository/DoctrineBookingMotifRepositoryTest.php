<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\BookingMotifEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineBookingMotifRepositoryTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER  = '01960000-0000-7000-8000-00000000c002';
    private const string MOTIF  = '01960000-0000-7000-8000-00000000b001';
    private const string VET_A  = '01960000-0000-7000-8000-00000000d001';
    private const string VET_B  = '01960000-0000-7000-8000-00000000d002';
    private const string VET_C  = '01960000-0000-7000-8000-00000000d003';

    private BookingMotifRepositoryInterface $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(BookingMotifRepositoryInterface::class);
        \assert($repository instanceof BookingMotifRepositoryInterface);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    public function testSaveAndFindRoundTripEveryField(): void
    {
        $this->repository->save($this->motif(self::MOTIF, 'Consultation', 0, [self::VET_A, self::VET_B]));
        $this->entityManager->clear();

        $loaded = $this->repository->findInClinic(BookingMotifId::fromString(self::MOTIF), ClinicId::fromString(self::CLINIC));

        self::assertNotNull($loaded);
        self::assertSame(self::MOTIF, $loaded->id()->toString());
        self::assertSame(self::CLINIC, $loaded->clinicId()->toString());
        self::assertSame('Consultation', $loaded->label());
        self::assertSame('Première visite', $loaded->description());
        self::assertSame(30, $loaded->durationMin());
        self::assertTrue($loaded->isOnlineEnabled());
        self::assertSame(ConfirmationMode::MANUAL, $loaded->confirmationKnownClient());
        self::assertSame([PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK], $loaded->allowedBlockTypes());
        self::assertSame([self::VET_A, self::VET_B], $this->practitioners($loaded));
        self::assertSame(BookingPreventiveCareKind::VACCINATION, $loaded->preventiveCareKind());
        self::assertTrue($loaded->newClientsAllowed());
        self::assertSame(0, $loaded->sortOrder());
        self::assertNull($loaded->archivedAt());
    }

    public function testAMotifIsNotFoundFromAnotherClinic(): void
    {
        $this->repository->save($this->motif(self::MOTIF, 'Consultation', 0, [self::VET_A]));

        self::assertNull($this->repository->findInClinic(BookingMotifId::fromString(self::MOTIF), ClinicId::fromString(self::OTHER)));
        self::assertNull($this->repository->findInClinic(BookingMotifId::fromString('01960000-0000-7000-8000-00000000b0ff'), ClinicId::fromString(self::CLINIC)));
    }

    public function testUpdateKeepsTheRemainingPractitionersAndArchives(): void
    {
        $this->repository->save($this->motif(self::MOTIF, 'Consultation', 0, [self::VET_A, self::VET_B]));
        $this->entityManager->clear();

        $motif = $this->repository->findInClinic(BookingMotifId::fromString(self::MOTIF), ClinicId::fromString(self::CLINIC));
        self::assertNotNull($motif);
        $motif->update('Vaccin', null, 15, false, ConfirmationMode::AUTO, [PlanningBlockType::SURGERY], [UserId::fromString(self::VET_B), UserId::fromString(self::VET_C)], null, false, 4);
        $motif->archive(new \DateTimeImmutable('2026-11-01 09:30:00'));
        $this->repository->save($motif);
        $this->entityManager->clear();

        $loaded = $this->repository->findInClinic(BookingMotifId::fromString(self::MOTIF), ClinicId::fromString(self::CLINIC));
        self::assertNotNull($loaded);
        self::assertSame('Vaccin', $loaded->label());
        self::assertNull($loaded->description());
        self::assertSame(15, $loaded->durationMin());
        self::assertFalse($loaded->isOnlineEnabled());
        self::assertSame(ConfirmationMode::AUTO, $loaded->confirmationKnownClient());
        self::assertSame([PlanningBlockType::SURGERY], $loaded->allowedBlockTypes());
        self::assertSame([self::VET_B, self::VET_C], $this->practitioners($loaded));
        self::assertNull($loaded->preventiveCareKind());
        self::assertFalse($loaded->newClientsAllowed());
        self::assertSame(4, $loaded->sortOrder());
        self::assertSame('2026-11-01 09:30:00', $loaded->archivedAt()?->format('Y-m-d H:i:s'));

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        self::assertSame(2, $connection->fetchOne(
            'SELECT COUNT(*) FROM scheduling__booking_motif_practitioners WHERE motif_id = ?',
            [Uuid::fromString(self::MOTIF)->toBinary()],
        ));

        $entity = $this->entityManager->find(BookingMotifEntity::class, Uuid::fromString(self::MOTIF));
        self::assertNotNull($entity);
        foreach ($entity->getPractitioners() as $row) {
            self::assertSame($entity, $row->getMotif());
        }
    }

    public function testListIsOrderedAndHidesArchivedMotifsUnlessAsked(): void
    {
        $this->repository->save($this->motif('01960000-0000-7000-8000-00000000b003', 'Vaccin', 1, [self::VET_A]));
        $this->repository->save($this->motif('01960000-0000-7000-8000-00000000b002', 'Consultation', 1, [self::VET_A]));
        $this->repository->save($this->motif('01960000-0000-7000-8000-00000000b001', 'Urgence', 0, [self::VET_A, self::VET_B]));
        $archived = $this->motif('01960000-0000-7000-8000-00000000b004', 'Ancien', 0, [self::VET_A]);
        $archived->archive(new \DateTimeImmutable('2026-10-01 08:00:00'));
        $this->repository->save($archived);
        $this->repository->save(BookingMotif::create(
            BookingMotifId::fromString('01960000-0000-7000-8000-00000000b005'),
            ClinicId::fromString(self::OTHER),
            'Autre clinique',
            null,
            15,
            true,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::VET_A)],
            null,
            false,
            0,
        ));
        $this->entityManager->clear();

        $active = $this->repository->listForClinic(ClinicId::fromString(self::CLINIC), false);
        self::assertSame(['Urgence', 'Consultation', 'Vaccin'], $this->labels($active));
        self::assertSame([self::VET_A, self::VET_B], $this->practitioners($active[0]));
        self::assertSame(['Ancien', 'Urgence', 'Consultation', 'Vaccin'], $this->labels($this->repository->listForClinic(ClinicId::fromString(self::CLINIC), true)));
    }

    /**
     * @param list<BookingMotif> $motifs
     *
     * @return list<string>
     */
    private function labels(array $motifs): array
    {
        return array_map(static fn (BookingMotif $motif): string => $motif->label(), $motifs);
    }

    /**
     * @return list<string>
     */
    private function practitioners(BookingMotif $motif): array
    {
        $ids = array_map(static fn (UserId $id): string => $id->toString(), $motif->practitionerIds());
        sort($ids);

        return $ids;
    }

    /**
     * @param list<string> $practitioners
     */
    private function motif(string $id, string $label, int $sortOrder, array $practitioners): BookingMotif
    {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            'Première visite',
            30,
            true,
            ConfirmationMode::MANUAL,
            [PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK],
            array_map(UserId::fromString(...), $practitioners),
            BookingPreventiveCareKind::VACCINATION,
            true,
            $sortOrder,
        );
    }
}
