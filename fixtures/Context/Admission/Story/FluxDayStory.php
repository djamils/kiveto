<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Admission\Story;

use App\Context\Admission\Application\Command\OpenAdmissionFromAppointment\OpenAdmissionFromAppointment;
use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckIn;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeight;
use App\Context\Billing\Application\Command\CheckoutAdmission\CheckoutAdmission;
use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId as BillingClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanAction;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The flow board's day around the clock, in the three clinics of the
 * flux-ambulatoire mock-ups (busy_8 for Paris, _3 for Lyon, calme_4 for the
 * Calanques): bookings honoured and paid this morning (the « passages
 * terminés » fold, with their booking time, what they paid and their report),
 * a no-show to reschedule, a booking running late, a patient whose arrival
 * window noted a weighing, a new symptom and a note, and a booking whose
 * owner still owes money from a previous visit (« Reste dû »).
 *
 * The bookings are written straight into the agenda (relative to the clock,
 * whatever time the fixtures are loaded) so they never collide with the
 * practitioners' planned day; everything else goes through the command bus.
 */
final class FluxDayStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;
    private const string CALM  = ClinicDataStory::CALM_CLINIC_ID;

    /** Paolo Costa (DirectoryCrowdStory) owes 86,50 € (DirectoryBalancesStory); Rio is his dog. */
    private const string COSTA = '01936e19-d1d1-7000-8000-000000000006';
    private const string RIO   = '01936e19-d2d2-7000-8000-000006000001';

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        $this->now = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'));

        $this->buildParis($this->userId('vet@kiveto.local'));
        $this->buildLyon($this->userId('vet.lyon@kiveto.local'));
        $this->buildCalm($this->userId('vet@kiveto.local'));
    }

    private function buildParis(string $vet): void
    {
        // Seen and gone this morning, booked: the fold shows « 08:15 · Parti à 08:41 · 96,00 € ».
        $nala = $this->createPatient(self::PARIS, 'Léa', 'Morel', '+33611223344', 'Nala', 'dog', 'Berger australien', '2021-04-02');
        $this->passage(self::PARIS, $vet, $nala, 'Nala', 'Vaccin CHPPiL + rage', 150, 128, [['VACC_CHPPIL', 'Vaccination CHPPiL', 1.0], ['CONS_STD', 'Consultation standard', 1.0]]);

        $grisou = $this->createPatient(self::PARIS, 'Marc', 'Duval', '+33622113355', 'Grisou', 'cat', 'Chartreux', '2016-09-12');
        $this->passage(self::PARIS, $vet, $grisou, 'Grisou', 'Otite externe', 100, 66, [['CONS_SUIVI', 'Consultation de suivi', 1.0]]);

        // Nobody came: « Non venu · Reprogrammer ».
        $cookie = $this->createPatient(self::PARIS, 'Lucas', 'Petit', '+33761020304', 'Cookie', 'dog', 'Labrador', '2022-02-20');
        $this->booking(self::PARIS, $vet, $cookie, 'Rappel vaccin', -85, AppointmentStatus::NO_SHOW);

        // Booked a quarter of an hour ago and not here yet: « En retard ».
        $nori = $this->createPatient(self::PARIS, 'Anna', 'Klein', '+33745544554', 'Nori', 'cat', 'Bengal', '2023-03-15');
        $this->commandBus->dispatch(new AddMedicalAlert(self::PARIS, $nori['animal'], 'BEHAVIOR', 'Sédation difficile', null));
        $this->booking(self::PARIS, $vet, $nori, 'Vaccin CHPPiL', -14, AppointmentStatus::PLANNED);

        // Arrived on time; the desk weighed her and noted what to tell the vet.
        $gaia = $this->createPatient(self::PARIS, 'Jules', 'Bonnet', '+33688776655', 'Gaïa', 'dog', 'Golden retriever', '2019-06-01');
        $this->weigh(self::PARIS, $gaia['animal'], 30_600, '-4 months');
        $visit = $this->arrival(self::PARIS, $vet, $gaia, 'Gaïa', 'Boiterie antérieure', 24);
        $this->commandBus->dispatch(new RecordArrivalCheckIn(
            clinicId: self::PARIS,
            admissionId: $visit,
            newSymptom: true,
            documentsBrought: true,
            weightGrams: 31_200,
            note: 'Boite depuis hier soir, mange moins.',
        ));
        $this->weigh(self::PARIS, $gaia['animal'], 31_200, 'today');
        $this->backdateAdmission($visit, 20, 20);

        // Coming for a scaling under anaesthesia (fasting asked) with 86,50 € left from a previous visit.
        $this->weigh(self::PARIS, self::RIO, 9_400, '-3 months');
        $this->booking(self::PARIS, $vet, ['client' => self::COSTA, 'animal' => self::RIO], 'Détartrage sous AG', 35, AppointmentStatus::PLANNED);
    }

    private function buildLyon(string $vet): void
    {
        $moustache = $this->createPatient(self::LYON, 'Claire', 'Vidal', '+33478112233', 'Moustache', 'cat', 'Européen', '2018-11-05');
        $this->passage(self::LYON, $vet, $moustache, 'Moustache', 'Vaccin leucose', 110, 78, [['CONS_STD', 'Consultation standard', 1.0]]);
    }

    /**
     * The quiet clinic's morning bookings (CalmDayClinicStory) were honoured
     * and paid: its fold lists two passages and nobody is on site.
     */
    private function buildCalm(string $vet): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(a.id) AS id, BIN_TO_UUID(a.animal_id) AS animal_id, BIN_TO_UUID(a.owner_id) AS owner_id,
                    a.reason, a.starts_at_utc, an.name
             FROM scheduling__appointments a JOIN animal__animals an ON an.id = a.animal_id
             WHERE a.clinic_id = :clinicId AND a.status = 'PLANNED' AND a.starts_at_utc < :noon
             ORDER BY a.starts_at_utc",
            [
                'clinicId' => Uuid::fromString(self::CALM)->toBinary(),
                'noon'     => $this->now->setTime(12, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        foreach ($rows as $i => $row) {
            \assert(\is_string($row['id']) && \is_string($row['animal_id']) && \is_string($row['name']) && \is_string($row['starts_at_utc']));
            $start   = new \DateTimeImmutable($row['starts_at_utc'], new \DateTimeZone('UTC'));
            $visit   = $this->honour(self::CALM, $row['id'], $row['animal_id'], $row['name'], \is_string($row['reason']) ? $row['reason'] : null);
            $minutes = (int) round(($this->now->getTimestamp() - $start->getTimestamp()) / 60);

            $this->bill(self::CALM, $vet, $visit, 0 === $i ? [['VACC_CHPPIL', 'Vaccination CHPPiL', 1.0], ['CONS_STD', 'Consultation standard', 1.0]] : [['CONS_SUIVI', 'Consultation de suivi', 1.0]]);

            // Loaded before the morning is over, the visits simply end now.
            if ($minutes > 40) {
                $this->backdateAdmission($visit, $minutes + 4, $minutes - 8, $minutes - (0 === $i ? 12 : 31));
            }
        }
    }

    /**
     * A booking honoured, consulted, billed and paid: the admission left
     * `$leftMinutesAgo`, the booking was `$bookedMinutesAgo`.
     *
     * @param array{client: string, animal: string}       $patient
     * @param list<array{0: string, 1: string, 2: float}> $acts
     */
    private function passage(string $clinicId, string $vet, array $patient, string $name, string $reason, int $bookedMinutesAgo, int $leftMinutesAgo, array $acts): void
    {
        $visit = $this->arrival($clinicId, $vet, $patient, $name, $reason, $bookedMinutesAgo);
        $this->bill($clinicId, $vet, $visit, $acts);
        $this->backdateAdmission($visit, $bookedMinutesAgo + 3, $leftMinutesAgo + 6, $leftMinutesAgo);
    }

    /**
     * @param array{client: string, animal: string} $patient
     */
    private function arrival(string $clinicId, string $vet, array $patient, string $name, string $reason, int $bookedMinutesAgo): string
    {
        $appointmentId = $this->booking($clinicId, $vet, $patient, $reason, -$bookedMinutesAgo, AppointmentStatus::PLANNED);

        return $this->honour($clinicId, $appointmentId, $patient['animal'], $name, $reason);
    }

    private function honour(string $clinicId, string $appointmentId, string $animalId, string $name, ?string $reason): string
    {
        $admissionId = $this->commandBus->dispatch(new OpenAdmissionFromAppointment(
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            knownAnimalId: $animalId,
            animalName: $name,
            triageNotes: $reason,
        ));
        \assert(\is_string($admissionId));

        $this->commandBus->dispatch(new CheckInAppointment($clinicId, $appointmentId, $admissionId));

        return $admissionId;
    }

    /**
     * @param list<array{0: string, 1: string, 2: float}> $acts catalog code, label, quantity
     */
    private function bill(string $clinicId, string $vet, string $admissionId, array $acts): void
    {
        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission($admissionId, $vet));
        \assert(\is_string($consultationId));

        $this->commandBus->dispatch(new RecordChiefComplaint($consultationId, $clinicId, 'Motif du rendez-vous.'));

        foreach ($acts as [$code, $label, $quantity]) {
            $this->commandBus->dispatch(new AddPlanAction(
                consultationId: $consultationId,
                clinicId: $clinicId,
                kind: 'PERFORMED_ACT',
                description: $label,
                catalogItemId: $this->actId($clinicId, $code),
                catalogCode: null,
                posology: null,
                durationDays: null,
                followUpDays: null,
                quantity: $quantity,
                createdByUserId: $vet,
            ));
        }

        $this->commandBus->dispatch(new CloseConsultation($consultationId, $vet, 'Examen sans particularité, soins réalisés.'));

        $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission($clinicId, $admissionId));
        \assert(\is_string($invoiceId));

        $invoice = $this->invoices->get(BillingClinicId::fromString($clinicId), InvoiceId::fromString($invoiceId));

        // Paid in full at the desk: the checkout ends the visit.
        $this->commandBus->dispatch(new CheckoutAdmission($clinicId, $invoiceId, 'CARD', $invoice->balanceDue()->minorUnits()));

        $entity = $this->entityManager->find(ConsultationEntity::class, Uuid::fromString($consultationId)->toBinary());

        if (null !== $entity && null !== $entity->getClosedAtUtc()) {
            $entity->setStartedAtUtc($entity->getClosedAtUtc()->modify('-17 minutes'));
            $this->entityManager->flush();
        }
    }

    /**
     * A booking `$minutesFromNow` away (negative: in the past), written
     * straight into the agenda.
     *
     * @param array{client: string, animal: string} $patient
     */
    private function booking(string $clinicId, string $vet, array $patient, string $reason, int $minutesFromNow, AppointmentStatus $status): string
    {
        $start = $this->now->modify(\sprintf('%+d minutes', $minutesFromNow));
        // On the quarter hour, as the desk books.
        $start = $start->setTime((int) $start->format('G'), intdiv((int) $start->format('i'), 15) * 15);

        $appointment = AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId($vet)
            ->startingAt($start->setTimezone(new \DateTimeZone('UTC')), 20)
            ->withStatus($status)
            ->create([
                'reason'   => $reason,
                'ownerId'  => Uuid::fromString($patient['client']),
                'animalId' => Uuid::fromString($patient['animal']),
            ])
        ;

        return $appointment->getId()->toRfc4122();
    }

    private function weigh(string $clinicId, string $animalId, int $grams, string $when): void
    {
        $this->commandBus->dispatch(new RecordAnimalWeight(
            clinicId: $clinicId,
            animalId: $animalId,
            weightGrams: $grams,
            measuredOn: $this->now->modify($when)->format('Y-m-d'),
            bodyConditionScore: null,
            authorName: 'Accueil',
        ));
    }

    /**
     * @return array{client: string, animal: string}
     */
    private function createPatient(string $clinicId, string $firstName, string $lastName, string $phone, string $animal, string $species, string $breed, string $birthDate): array
    {
        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: [new ContactMethodDto(type: 'phone', label: 'mobile', value: $phone, isPrimary: true)],
        ));
        \assert(\is_string($clientId));

        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $animal,
            species: $species,
            sex: 'unknown',
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: $breed,
            birthDate: $birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        return ['client' => $clientId, 'animal' => $animalId];
    }

    private function backdateAdmission(string $admissionId, int $openedMinutesAgo, int $placeMinutesAgo, ?int $closedMinutesAgo = null): void
    {
        $entity = $this->entityManager->find(AdmissionEntity::class, Uuid::fromString($admissionId));

        if (null === $entity) {
            return;
        }

        $now = $entity->getCreatedAt();
        $entity->setOpenedAt($now->modify(\sprintf('-%d minutes', $openedMinutesAgo)));
        $entity->setLocationStatusEnteredAt($now->modify(\sprintf('-%d minutes', max(0, $placeMinutesAgo))));

        if (null !== $closedMinutesAgo) {
            $entity->setClosedAt($now->modify(\sprintf('-%d minutes', max(0, $closedMinutesAgo))));
        }

        $this->entityManager->flush();
    }

    private function userId(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
    }

    private function actId(string $clinicId, string $code): string
    {
        $act = $this->entityManager->getRepository(ActEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString($clinicId),
        ]);

        if (null === $act) {
            throw new \RuntimeException(\sprintf('Catalog act "%s" not found.', $code));
        }

        return $act->getId()->toRfc4122();
    }
}
