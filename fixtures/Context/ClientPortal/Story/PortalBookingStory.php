<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\ClientPortal\Application\Command\BookPortalAppointment\BookPortalAppointment;
use App\Context\ClientPortal\Application\Command\CancelPortalAppointment\CancelPortalAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\GetOnlineAvailability;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\OnlineAvailability;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\PortalInvitationEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\Context\Scheduling\Story\OnlineBookingStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Portal appointments and booking (slice 4b), on the accounts of slices
 * 2–3, built on OnlineBookingStory (settings, motifs, closed / full / leave
 * days). Future bookings go through the ClientPortal commands
 * (`BookPortalAppointment`, `CancelPortalAppointment`) on slots the
 * availability query offers; the past is written with the factory.
 *
 * | Account (phone)                 | Clinic  | What the Portal shows                                                     |
 * |---------------------------------|---------|---------------------------------------------------------------------------|
 * | Sophie Garnier (+33612345679)   | Paris   | upcoming: Pepito confirmed, Nala pending (both after Oscar's home         |
 * |                                 |         | appointment in 3 days); past scope: Coco cancelled by her, Pepito         |
 * |                                 |         | cancelled by the clinic, Oscar declined (future slots, withdrawn), and 22 |
 * |                                 |         | past visits (completed, no-show, cancelled by her / by the clinic,        |
 * |                                 |         | declined); Filou deceased: not bookable                                   |
 * |                                 | Lyon    | new link, Moka: one booking 3 days ago inside the 72 h window (not        |
 * |                                 |         | cancellable), one booked just now inside the window (15-minute grace)     |
 * | Julien Girard (+33612345678)    | Paris   | Oslo confirmed after his home appointment in 9 days                       |
 * | Thierry Marchand (+33612345680) | Paris   | Gaston confirmed and Sésame pending (his second client file), after       |
 * |                                 |         | Gaston's home appointment in 12 days                                      |
 * |                                 | Cassis  | new link: online booking disabled (`booking_not_open`)                    |
 * |                                 | Vercors | new link: empty opening week, every day closed                            |
 *
 * The « booked just now » case holds for 15 minutes after the fixtures load.
 */
final class PortalBookingStory extends Story
{
    /** Sophie Garnier's file in Lyon (Moka). */
    public const string GARNIER_LYON_ID = '019a3d00-0000-7000-8000-0000000000d1';

    /** Thierry Marchand's file in Cassis, where online booking is off. */
    public const string MARCHAND_CASSIS_ID = '019a3d00-0000-7000-8000-0000000000d2';

    /** Thierry Marchand's file in Vercors, whose opening week is empty. */
    public const string MARCHAND_VERCORS_ID = '019a3d00-0000-7000-8000-0000000000d3';

    private const string PARIS  = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON   = ClinicDataStory::GROUP_CLINIC_ID;
    private const string CASSIS = ClinicDataStory::CALM_CLINIC_ID;
    private const string TZ     = 'Europe/Paris';

    /** Lyon's cancellation window (OnlineBookingStory). */
    private const int LYON_WINDOW_HOURS = 72;

    /**
     * Sophie Garnier's past in Paris: [when, pet, motif label or null (booked
     * at the desk), status, cancelled by, cancellation reason].
     *
     * @var list<array{string, string, ?string, AppointmentStatus, ?CancellationActor, ?CancellationReason}>
     */
    private const array GARNIER_PAST = [
        ['-700 days 10:00', 'Oscar', null, AppointmentStatus::COMPLETED, null, null],
        ['-640 days 17:30', 'Nala', null, AppointmentStatus::COMPLETED, null, null],
        ['-600 days 09:30', 'Pepito', null, AppointmentStatus::COMPLETED, null, null],
        ['-560 days 18:00', 'Filou', null, AppointmentStatus::COMPLETED, null, null],
        ['-520 days 11:00', 'Coco', null, AppointmentStatus::COMPLETED, null, null],
        ['-480 days 16:30', 'Oscar', null, AppointmentStatus::NO_SHOW, null, null],
        ['-430 days 10:15', 'Oscar', 'Consultation', AppointmentStatus::COMPLETED, null, null],
        ['-390 days 17:00', 'Nala', 'Vaccination', AppointmentStatus::COMPLETED, null, null],
        ['-350 days 09:00', 'Pepito', 'Consultation', AppointmentStatus::CANCELLED, CancellationActor::CLIENT, CancellationReason::SEEN_ELSEWHERE],
        ['-330 days 14:30', 'Coco', 'Vaccination', AppointmentStatus::COMPLETED, null, null],
        ['-300 days 18:15', 'Oscar', 'Contrôle / suivi', AppointmentStatus::COMPLETED, null, null],
        ['-280 days 10:45', 'Filou', 'Consultation', AppointmentStatus::COMPLETED, null, null],
        ['-250 days 15:00', 'Oscar', 'Contrôle / suivi', AppointmentStatus::DECLINED, null, null],
        ['-230 days 09:15', 'Nala', 'Consultation', AppointmentStatus::COMPLETED, null, null],
        ['-200 days 11:30', 'Oscar', 'Vaccination', AppointmentStatus::COMPLETED, null, null],
        ['-170 days 17:45', 'Pepito', null, AppointmentStatus::CANCELLED, CancellationActor::CLINIC, null],
        ['-150 days 16:00', 'Coco', 'Consultation', AppointmentStatus::COMPLETED, null, null],
        ['-120 days 10:00', 'Oscar', 'Contrôle / suivi', AppointmentStatus::COMPLETED, null, null],
        ['-90 days 18:30', 'Nala', 'Consultation', AppointmentStatus::CANCELLED, CancellationActor::CLIENT, CancellationReason::UNAVAILABLE],
        ['-60 days 09:45', 'Pepito', 'Consultation', AppointmentStatus::NO_SHOW, null, null],
        ['-30 days 14:15', 'Oscar', 'Contrôle / suivi', AppointmentStatus::COMPLETED, null, null],
        ['-12 days 17:15', 'Coco', 'Consultation', AppointmentStatus::COMPLETED, null, null],
    ];

    private \DateTimeImmutable $now;

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalPetsStory::load();
        OnlineBookingStory::load();

        $this->now   = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ));
        $this->today = $this->now->setTime(0, 0);

        $rousseau = $this->user('vet@kiveto.local');
        $dupont   = $this->user('contractor@kiveto.local');
        $paris    = $this->motifs(self::PARIS);

        $this->garnierParis($paris, $rousseau, $dupont);
        $this->garnierHistory($paris, $rousseau, $dupont);
        $this->garnierLyon();
        $this->girardParis($paris, $rousseau, $dupont);
        $this->marchandParis($paris, $rousseau, $dupont);
        $this->marchandElsewhere();
    }

    /**
     * Sophie Garnier's bookings in Paris, all after Oscar's home appointment
     * (day 3, 09:00): two to come, three withdrawn.
     *
     * @param array<string, BookingMotifView> $motifs
     */
    private function garnierParis(array $motifs, string $rousseau, string $dupont): void
    {
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE_WITH_EMAIL);
        $client = ClientSheetStatesStory::CLIENT_BUSY_ID;
        $vets   = [$dupont, $rousseau];

        // Upcoming: confirmed at once (auto motif), and waiting for the clinic (manual motif)
        $this->book($userId, self::PARIS, $this->animalOf($client, 'Pepito'), $motifs['Consultation'], $vets, 4, '17:00');
        $this->book($userId, self::PARIS, $this->animalOf($client, 'Nala'), $motifs['Contrôle / suivi'], [$rousseau], 4, '10:00');

        // Cancelled by her on the Portal
        $cancelled = $this->book($userId, self::PARIS, $this->animalOf($client, 'Coco'), $motifs['Consultation'], $vets, 5, '11:00');
        $this->commandBus->dispatch(new CancelPortalAppointment($userId, self::PARIS, $cancelled, 'no_longer_needed'));

        // Cancelled by the clinic (the vet is away that day)
        $cancelledByClinic = $this->book($userId, self::PARIS, $this->animalOf($client, 'Pepito'), $motifs['Consultation'], $vets, 6, '15:00');
        $this->commandBus->dispatch(new CancelAppointment($cancelledByClinic, self::PARIS, cancelledBy: 'clinic'));

        // Declined by the clinic
        $declined = $this->book($userId, self::PARIS, $this->animalOf($client, 'Oscar'), $motifs['Contrôle / suivi'], [$rousseau], 8, '16:00');
        $this->commandBus->dispatch(new DeclineAppointment($declined, self::PARIS));
    }

    /**
     * More than 20 past appointments of Sophie Garnier in Paris, in every
     * outcome: booked at the desk or online.
     *
     * @param array<string, BookingMotifView> $motifs
     */
    private function garnierHistory(array $motifs, string $rousseau, string $dupont): void
    {
        $client = ClientSheetStatesStory::CLIENT_BUSY_ID;
        $utc    = new \DateTimeZone('UTC');

        foreach (self::GARNIER_PAST as $i => [$when, $pet, $motifLabel, $status, $cancelledBy, $reason]) {
            $start   = $this->today->modify($when)->setTimezone($utc);
            $motif   = null === $motifLabel ? null : $motifs[$motifLabel];
            $animal  = $this->animalOf($client, $pet);
            $booked  = $start->modify('-9 days');
            $changed = AppointmentStatus::CANCELLED === $status || AppointmentStatus::DECLINED === $status ? $start->modify('-2 days') : $start->modify('+1 hour');

            AppointmentEntityFactory::new()
                ->withClinicId(self::PARIS)
                ->withPractitionerUserId(0 === $i % 2 ? $rousseau : $dupont)
                ->startingAt($start, $motif->durationMin ?? 30)
                ->withStatus($status)
                ->withOwnerAndAnimal($client, $animal)
                ->withReason($motif->label ?? 'Consultation')
                ->afterInstantiate(static function (AppointmentEntity $entity) use ($motif, $cancelledBy, $reason): void {
                    $entity->setSource(null === $motif ? AppointmentSource::CLINIC : AppointmentSource::PORTAL);
                    $entity->setBookingMotifId(null === $motif ? null : Uuid::fromString($motif->id));
                    $entity->setCancelledBy($cancelledBy);
                    $entity->setCancellationReason($reason);
                })
                ->create(['createdAt' => $booked, 'updatedAt' => $changed])
            ;
        }
    }

    /**
     * Sophie Garnier is also a client in Lyon (Moka), where the cancellation
     * window is 72 h: one booking made 3 days ago that has entered it, one
     * booked just now inside it (cancellable for 15 minutes).
     */
    private function garnierLyon(): void
    {
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE_WITH_EMAIL);

        $this->client(self::LYON, self::GARNIER_LYON_ID, 'madam', 'Sophie', 'Garnier', PortalUserStory::DEMO_PHONE_WITH_EMAIL, ['3 place Bellecour', '69002', 'Lyon'], '-1 year');
        $moka = $this->pet(self::LYON, self::GARNIER_LYON_ID, 'Moka', 'dog', 'female', 'Cocker anglais', '2023-03-18');
        $this->link($userId, self::LYON, self::GARNIER_LYON_ID, '-20 days');

        $motif = $this->motifs(self::LYON)['Consultation'];
        $limit = $this->now->modify(\sprintf('+%d hours', self::LYON_WINDOW_HOURS));

        $old = $this->book($userId, self::LYON, $moka, $motif, $motif->practitionerIds, 0, '00:00', $limit);
        $this->backdate($old, $this->now->modify('-3 days'));

        $this->book($userId, self::LYON, $moka, $motif, $motif->practitionerIds, 0, '00:00', $limit);
    }

    /**
     * Julien Girard books Oslo in Paris, after his home appointment (day 9).
     *
     * @param array<string, BookingMotifView> $motifs
     */
    private function girardParis(array $motifs, string $rousseau, string $dupont): void
    {
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE);
        $client = $this->clientWithPhone(self::PARIS, 'Girard', PortalUserStory::DEMO_PHONE);

        $this->book($userId, self::PARIS, $this->animalOf($client, 'Oslo'), $motifs['Consultation'], [$rousseau, $dupont], 10, '11:00');
    }

    /**
     * Thierry Marchand books on both his Paris files, after Gaston's home
     * appointment (day 12).
     *
     * @param array<string, BookingMotifView> $motifs
     */
    private function marchandParis(array $motifs, string $rousseau, string $dupont): void
    {
        $userId = $this->portalUserId(PortalDocumentsStory::MARCHAND_PHONE);

        $this->book($userId, self::PARIS, $this->animalOf(ClientSheetStatesStory::CLIENT_NORMAL_ID, 'Gaston'), $motifs['Consultation'], [$dupont, $rousseau], 13, '17:30');
        $this->book($userId, self::PARIS, $this->animalOf(PortalPetsAnimalStory::MARCHAND_SECOND_FILE_ID, 'Sésame'), $motifs['Contrôle / suivi'], [$rousseau], 13, '09:30');
    }

    /** Thierry Marchand is also a client where the Portal cannot book. */
    private function marchandElsewhere(): void
    {
        $userId = $this->portalUserId(PortalDocumentsStory::MARCHAND_PHONE);

        $this->client(self::CASSIS, self::MARCHAND_CASSIS_ID, 'mister', 'Thierry', 'Marchand', PortalDocumentsStory::MARCHAND_PHONE, ['12 avenue de la Gare', '13260', 'Cassis'], '-2 years');
        $this->pet(self::CASSIS, self::MARCHAND_CASSIS_ID, 'Galet', 'cat', 'male', 'Européen', '2020-07-01');
        $this->link($userId, self::CASSIS, self::MARCHAND_CASSIS_ID, '-15 days');

        $this->client(OnlineBookingStory::EMPTY_WEEK_CLINIC_ID, self::MARCHAND_VERCORS_ID, 'mister', 'Thierry', 'Marchand', PortalDocumentsStory::MARCHAND_PHONE, ['4 route de Lans', '38250', 'Villard-de-Lans'], '-6 months');
        $this->pet(OnlineBookingStory::EMPTY_WEEK_CLINIC_ID, self::MARCHAND_VERCORS_ID, 'Névé', 'dog', 'female', 'Berger des Pyrénées', '2022-01-09');
        $this->link($userId, OnlineBookingStory::EMPTY_WEEK_CLINIC_ID, self::MARCHAND_VERCORS_ID, '-5 days');
    }

    /**
     * Books the pet on the first slot the Portal offers, from day `$fromDay`
     * on (14 days searched), at or after `$notBefore` local time on its day
     * when that day has one, before `$before` when given.
     *
     * @param list<string> $practitionerIds
     */
    private function book(
        string $userId,
        string $clinicId,
        string $petId,
        BookingMotifView $motif,
        array $practitionerIds,
        int $fromDay,
        string $notBefore,
        ?\DateTimeImmutable $before = null,
    ): string {
        $from = $this->today->modify(\sprintf('+%d days', $fromDay));
        $best = null;

        foreach ($practitionerIds as $practitionerId) {
            $availability = $this->queryBus->ask(new GetOnlineAvailability(
                clinicId: $clinicId,
                motifId: $motif->id,
                practitionerId: $practitionerId,
                fromLocalDate: $from->format('Y-m-d'),
                toLocalDate: $from->modify('+13 days')->format('Y-m-d'),
            ));
            \assert($availability instanceof OnlineAvailability);

            $slot = $this->pick($availability, $notBefore, $before);

            if (null !== $slot && (null === $best || $slot < $best[1])) {
                $best = [$practitionerId, $slot];
            }
        }

        if (null === $best) {
            throw new \RuntimeException(\sprintf('No online slot for « %s » in clinic %s from %s.', $motif->label, $clinicId, $from->format('Y-m-d')));
        }

        $id = $this->commandBus->dispatch(new BookPortalAppointment(
            userId: $userId,
            clinicId: $clinicId,
            petId: $petId,
            motifId: $motif->id,
            practitionerId: $best[0],
            startsAt: $best[1],
        ));
        \assert(\is_string($id));

        return $id;
    }

    /** The first slot of the first day that has one, preferably at or after `$notBefore`. */
    private function pick(OnlineAvailability $availability, string $notBefore, ?\DateTimeImmutable $before): ?\DateTimeImmutable
    {
        foreach ($availability->days as $day) {
            $slots = array_values(array_filter(
                $day->slots,
                static fn (\DateTimeImmutable $slot): bool => null === $before || $slot < $before,
            ));

            if ([] === $slots) {
                continue;
            }

            foreach ($slots as $slot) {
                if ($slot->format('H:i') >= $notBefore) {
                    return $slot;
                }
            }

            return $slots[0];
        }

        return null;
    }

    /** The booking was made earlier than the fixtures load: it has entered its window since. */
    private function backdate(string $appointmentId, \DateTimeImmutable $at): void
    {
        $utc = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'UPDATE scheduling__appointments SET created_at_utc = ?, updated_at_utc = ? WHERE id = ?',
            [$utc, $utc, Uuid::fromString($appointmentId)->toBinary()],
        );
    }

    /**
     * The clinic's online motifs, by label.
     *
     * @return array<string, BookingMotifView>
     */
    private function motifs(string $clinicId): array
    {
        $motifs = $this->queryBus->ask(new ListBookingMotifs($clinicId));
        \assert(\is_array($motifs));

        $byLabel = [];

        foreach ($motifs as $motif) {
            \assert($motif instanceof BookingMotifView);
            $byLabel[$motif->label] = $motif;
        }

        return $byLabel;
    }

    /** @param array{0: string, 1: string, 2: string} $address street, postal code, city */
    private function client(string $clinicId, string $id, string $civility, string $firstName, string $lastName, string $phone, array $address, string $since): void
    {
        $sinceAt = $this->now->modify($since)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withName($firstName, $lastName)
            ->withCivility($civility)
            ->since($sinceAt)
            ->withPostalAddress(streetLine1: $address[0], city: $address[2], countryCode: 'FR', postalCode: $address[1])
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $sinceAt)
            ->active()
            ->create()
        ;
        ContactMethodEntityFactory::new()->forClient($id)->mobile()->phone($phone)->primary()->create();
    }

    private function pet(string $clinicId, string $clientId, string $name, string $species, string $sex, string $breed, string $birthDate): string
    {
        $id = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $name,
            species: $species,
            sex: $sex,
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
        \assert(\is_string($id));

        return $id;
    }

    /** An accepted invitation and the confirmed link it made (the owner profile already exists). */
    private function link(string $userId, string $clinicId, string $clientId, string $since): void
    {
        $linkedAt = $this->clock->now()->modify($since);
        $phone    = $this->connection->fetchOne('SELECT phone FROM identity_access__users WHERE id = ?', [Uuid::fromString($userId)->toBinary()]);
        \assert(\is_string($phone));

        PortalInvitationEntityFactory::new()
            ->forClient($clinicId, $clientId, $phone, null)
            ->sentAt($linkedAt->modify('-1 day'))
            ->acceptedBy($userId, $linkedAt)
            ->create()
        ;
        ClientLinkEntityFactory::new()->between($userId, $clinicId, $clientId)->linkedAt($linkedAt)->create();
    }

    private function animalOf(string $clientId, string $name): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(a.id) FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id AND o.role = 'primary'
             WHERE o.client_id = ? AND a.name = ? LIMIT 1",
            [Uuid::fromString($clientId)->toBinary(), $name],
        );
        \assert(\is_string($id), \sprintf('The dataset has no animal "%s" for client %s.', $name, $clientId));

        return $id;
    }

    private function clientWithPhone(string $clinicId, string $lastName, string $phone): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(c.id) FROM client__clients c INNER JOIN client__contact_methods m ON m.client_id = c.id
             WHERE c.clinic_id = ? AND c.last_name = ? AND m.value = ? LIMIT 1',
            [Uuid::fromString($clinicId)->toBinary(), $lastName, $phone],
        );
        \assert(\is_string($id));

        return $id;
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), 'The slice 2 stories create the demo owner accounts.');

        return $id;
    }

    private function user(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
    }
}
