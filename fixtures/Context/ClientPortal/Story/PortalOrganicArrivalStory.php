<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion\AcceptLinkSuggestion;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistration;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationOutcome;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationResult;
use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequest;
use App\Context\ClientPortal\Application\Command\RejectClientLink\RejectClientLink;
use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBooking;
use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointment;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\LinkSuggestion;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\ListLinkSuggestions;
use App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment\DeclineExpiredPendingAppointment;
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointments;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\GetOnlineAvailability;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\OnlineAvailability;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\ClinicListing\Story\ClinicListingStory;
use App\Fixtures\Context\Scheduling\Story\OnlineBookingStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpOutcome;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtp;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The organic arrival (slice 7): owners who came to the Portal without an
 * invitation, registered through the real sign-in (code read back from the
 * message journal, registration grant, `CompletePortalRegistration`), then
 * found their clinics or a new one in the directory (ClinicListingStory).
 *
 * | Account (phone)                | Where                    | State                                                        |
 * |--------------------------------|--------------------------|--------------------------------------------------------------|
 * | Élodie Garcia (+33645120701)   | Portal « Mes cliniques » | **2 suggestions**: Lyon (her file and Marc Garcia's, the     |
 * |                                |                          | family sharing her mobile, grouped) and the Calanques         |
 * |                                | VetApp Paris             | **pending « Rattachement »**, file « Élodie Vasseur » (her   |
 * |                                |                          | maiden name): « nom différent »                               |
 * |                                | Vercors                  | **rejected** by the clinic: never suggested again             |
 * | Hugo Masson (+33645120702)     | VetApp Lyon, « Nouveau   | **pending** request for Biscotte (cat), received now         |
 * |                                | client »                 |                                                              |
 * |                                | Portal « Mes demandes »  | one **declined** by Lyon, one **expired** (requested 50 h    |
 * |                                |                          | ago, unanswered, declined by the 15-minute scan)             |
 * | Léa Brunet (+33645120703)      | Lyon                     | **confirmed** (new client file, new pet Gaïa): linked, her   |
 * |                                |                          | appointment planned                                          |
 *
 * The expired request's decline was queued on `async` by the scan; it is
 * run here at once with the sync its integration event triggers (as the
 * worker would), the queued copies find it done. Dates follow the clock of
 * the load.
 */
final class PortalOrganicArrivalStory extends Story
{
    public const string GARCIA_PHONE = '+33645120701';
    public const string MASSON_PHONE = '+33645120702';
    public const string BRUNET_PHONE = '+33645120703';

    private const string PARIS   = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON    = ClinicDataStory::GROUP_CLINIC_ID;
    private const string CASSIS  = ClinicDataStory::CALM_CLINIC_ID;
    private const string VERCORS = OnlineBookingStory::EMPTY_WEEK_CLINIC_ID;
    private const string TZ      = 'Europe/Paris';

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private readonly string $termsVersion,
    ) {
    }

    public function build(): void
    {
        ClinicListingStory::load();

        $this->now = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ));

        $this->suggestions();
        $this->bookingRequests();
    }

    /**
     * Élodie Garcia is known to four clinics under her mobile; the Portal
     * suggests them, she asks two of them to link her.
     */
    private function suggestions(): void
    {
        $vasseur = $this->client(self::PARIS, 'Élodie', 'Vasseur', self::GARCIA_PHONE, ['8 rue des Martyrs', '75009', 'Paris'], '-6 years');
        $this->pet(self::PARIS, $vasseur, 'Tango', 'dog', 'male', 'Beagle', '2019-04-12');

        $garcia = $this->client(self::LYON, 'Élodie', 'Garcia', self::GARCIA_PHONE, ['14 rue Mercière', '69002', 'Lyon'], '-3 years');
        $this->pet(self::LYON, $garcia, 'Pistache', 'cat', 'female', 'Chartreux', '2021-09-02');
        $marc = $this->client(self::LYON, 'Marc', 'Garcia', self::GARCIA_PHONE, ['14 rue Mercière', '69002', 'Lyon'], '-2 years');
        $this->pet(self::LYON, $marc, 'Hercule', 'dog', 'male', 'Bouledogue français', '2020-01-20');

        $this->client(self::CASSIS, 'Élodie', 'Garcia', self::GARCIA_PHONE, ['3 avenue des Calanques', '13260', 'Cassis'], '-1 year');

        $vercors = $this->client(self::VERCORS, 'Élodie', 'Garcia', self::GARCIA_PHONE, ['5 chemin des Gentianes', '38250', 'Villard-de-Lans'], '-4 years');
        $this->pet(self::VERCORS, $vercors, 'Myrtille', 'cat', 'female', 'Européen', '2018-06-30');

        $userId      = $this->register(self::GARCIA_PHONE, 'Élodie', 'Garcia', 'elodie.garcia@gmail.com');
        $suggestions = $this->suggestionsOf($userId);

        // The Vercors does not recognise her: the request is rejected.
        $this->commandBus->dispatch(new AcceptLinkSuggestion($userId, $suggestions['Clinique du Vercors']));
        $rejected = $this->commandBus->dispatch(new RejectClientLink(self::VERCORS, $userId, [$vercors]));
        \assert(1 === $rejected);

        // Paris knows her under her maiden name: pending, « nom différent ».
        $this->commandBus->dispatch(new AcceptLinkSuggestion($userId, $suggestions['Clinique Vétérinaire de Paris']));

        $left = array_keys($this->suggestionsOf($userId));
        sort($left);
        \assert(['Clinique Vétérinaire de Lyon', 'Clinique des Calanques'] === $left, implode(', ', $left));
    }

    /**
     * New owners ask Lyon, listed and open to new clients, for a first
     * appointment through the directory.
     */
    private function bookingRequests(): void
    {
        $lemoine = $this->user('vet.lyon@kiveto.local');
        $motif   = $this->newClientMotif();

        $masson = $this->register(self::MASSON_PHONE, 'Hugo', 'Masson', null);

        // Requested 50 hours ago, never answered: declined by the scan.
        $expired = $this->book($masson, 'Biscotte', 'cat', $motif, 3);
        $this->expire($expired);

        $declined = $this->book($masson, 'Biscotte', 'cat', $motif, 5);
        $this->commandBus->dispatch(new DeclineBookingRequest(self::LYON, $declined, $lemoine));

        $this->book($masson, 'Biscotte', 'cat', $motif, 8);

        $brunet    = $this->register(self::BRUNET_PHONE, 'Léa', 'Brunet', 'lea.brunet@orange.fr');
        $confirmed = $this->book($brunet, 'Gaïa', 'dog', $motif, 4);
        $this->commandBus->dispatch(new ConfirmBookingRequest(
            clinicId: self::LYON,
            requestId: $confirmed,
            clientChoice: ConfirmBookingRequest::NEW,
            clientId: null,
            animalChoice: ConfirmBookingRequest::NEW,
            animalId: null,
            actorUserId: $lemoine,
        ));
    }

    /**
     * The request was made 50 hours before the load and expired 2 hours ago;
     * the 15-minute scan queues its decline, run here at once, then what
     * Scheduling's integration event makes ClientPortal do.
     */
    private function expire(string $requestId): void
    {
        $utc         = new \DateTimeZone('UTC');
        $requestedAt = $this->now->modify('-50 hours')->setTimezone($utc)->format('Y-m-d H:i:s');
        $expiredAt   = $this->now->modify('-2 hours')->setTimezone($utc)->format('Y-m-d H:i:s');
        $request     = Uuid::fromString($requestId)->toBinary();

        $appointmentId = $this->connection->fetchOne('SELECT BIN_TO_UUID(appointment_id) FROM client_portal__booking_requests WHERE id = ?', [$request]);
        \assert(\is_string($appointmentId));

        $this->connection->executeStatement('UPDATE client_portal__booking_requests SET created_at = ? WHERE id = ?', [$requestedAt, $request]);
        $this->connection->executeStatement(
            'UPDATE scheduling__appointments SET created_at_utc = ?, updated_at_utc = ?, pending_expires_at = ? WHERE id = ?',
            [$requestedAt, $requestedAt, $expiredAt, Uuid::fromString($appointmentId)->toBinary()],
        );

        $this->commandBus->dispatch(new ScanExpiredPendingAppointments());
        $this->commandBus->dispatch(new DeclineExpiredPendingAppointment($appointmentId), new TransportNamesStamp(['sync']));
        $this->commandBus->dispatch(new SyncBookingRequestFromAppointment($appointmentId, SyncBookingRequestFromAppointment::EXPIRED));
    }

    /**
     * The owner signs in with a number the Portal does not know: the code
     * (read back from the journal) gives a registration grant, which creates
     * the account with the current terms.
     */
    private function register(string $phone, string $firstName, string $lastName, ?string $email): string
    {
        $issued = $this->commandBus->dispatch(new RequestPortalOtp($phone, 'login'));
        \assert($issued instanceof OtpChallengeIssued);

        $outcome = $this->commandBus->dispatch(new VerifyPortalLoginOtp($issued->challengeId, $this->codeOf($issued->challengeId)));
        \assert($outcome instanceof LoginOtpOutcome && LoginOtpResult::REGISTRATION_REQUIRED === $outcome->result && null !== $outcome->onboardingToken);

        $registration = $this->commandBus->dispatch(new CompletePortalRegistration($outcome->onboardingToken, $firstName, $lastName, $email, $this->termsVersion));
        \assert($registration instanceof RegistrationOutcome && RegistrationResult::REGISTERED === $registration->result && null !== $registration->userId);

        return $registration->userId;
    }

    /**
     * The account's suggestions, their opaque ids by clinic name.
     *
     * @return array<string, string>
     */
    private function suggestionsOf(string $userId): array
    {
        $suggestions = $this->queryBus->ask(new ListLinkSuggestions($userId));
        \assert(\is_array($suggestions));

        $byName = [];

        foreach ($suggestions as $suggestion) {
            \assert($suggestion instanceof LinkSuggestion);
            $byName[$suggestion->clinicName] = $suggestion->id;
        }

        return $byName;
    }

    /** Lyon's motif open online to new clients. */
    private function newClientMotif(): BookingMotifView
    {
        $motifs = $this->queryBus->ask(new ListBookingMotifs(self::LYON, newClientsOnly: true));
        \assert(\is_array($motifs) && [] !== $motifs && $motifs[0] instanceof BookingMotifView);

        return $motifs[0];
    }

    /**
     * Asks Lyon through its listing for the first slot a new client is
     * offered from day `$fromDay` on (14 days searched).
     */
    private function book(string $userId, string $petName, string $species, BookingMotifView $motif, int $fromDay): string
    {
        $listingId = $this->connection->fetchOne('SELECT BIN_TO_UUID(id) FROM clinic_listing__clinic_listings WHERE clinic_id = ?', [Uuid::fromString(self::LYON)->toBinary()]);
        \assert(\is_string($listingId), 'ClinicListingStory projects Lyon.');

        $from = $this->now->setTime(0, 0)->modify(\sprintf('+%d days', $fromDay));
        $best = null;

        foreach ($motif->practitionerIds as $practitionerId) {
            $availability = $this->queryBus->ask(new GetOnlineAvailability(
                clinicId: self::LYON,
                motifId: $motif->id,
                practitionerId: $practitionerId,
                fromLocalDate: $from->format('Y-m-d'),
                toLocalDate: $from->modify('+13 days')->format('Y-m-d'),
                newClient: true,
            ));
            \assert($availability instanceof OnlineAvailability);

            foreach ($availability->days as $day) {
                if ([] !== $day->slots) {
                    if (null === $best || $day->slots[0] < $best[1]) {
                        $best = [$practitionerId, $day->slots[0]];
                    }

                    break;
                }
            }
        }

        if (null === $best) {
            throw new \RuntimeException(\sprintf('No new-client slot in Lyon from %s.', $from->format('Y-m-d')));
        }

        $id = $this->commandBus->dispatch(new RequestListingBooking(
            userId: $userId,
            listingId: $listingId,
            petName: $petName,
            petSpecies: $species,
            motifId: $motif->id,
            practitionerId: $best[0],
            startsAt: $best[1],
        ));
        \assert(\is_string($id));

        return $id;
    }

    /** @param array{0: string, 1: string, 2: string} $address street, postal code, city */
    private function client(string $clinicId, string $firstName, string $lastName, string $phone, array $address, string $since): string
    {
        $id      = Uuid::v7()->toRfc4122();
        $sinceAt = $this->now->modify($since)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withName($firstName, $lastName)
            ->withCivility('Marc' === $firstName ? 'mister' : 'madam')
            ->since($sinceAt)
            ->withPostalAddress(streetLine1: $address[0], city: $address[2], countryCode: 'FR', postalCode: $address[1])
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $sinceAt)
            ->active()
            ->create()
        ;
        ContactMethodEntityFactory::new()->forClient($id)->mobile()->phone($phone)->primary()->create();

        return $id;
    }

    private function pet(string $clinicId, string $clientId, string $name, string $species, string $sex, string $breed, string $birthDate): void
    {
        $this->commandBus->dispatch(new CreateAnimal(
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
    }

    /** The code of a challenge, as the journal of the first message sent for it reads. */
    private function codeOf(string $challengeId): string
    {
        $content = $this->connection->fetchOne('SELECT content FROM notification__outbound_messages WHERE idempotency_key = ?', ['otp:' . $challengeId . ':1']);
        if (!\is_string($content) || 1 !== preg_match('/(?<![#\d])(\d{6})(?!\d)/', $content, $match)) {
            throw new \LogicException(\sprintf('No code was sent for challenge %s.', $challengeId));
        }

        return $match[1];
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
