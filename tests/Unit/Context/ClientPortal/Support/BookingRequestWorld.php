<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequestHandler;
use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequestHandler;
use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBookingHandler;
use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointmentHandler;
use App\Context\ClientPortal\Application\Port\BookingMotifsInterface;
use App\Context\ClientPortal\Application\Port\ClientRegistrationInterface;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicListingsInterface;
use App\Context\ClientPortal\Application\Port\ListingBookingQuotaInterface;
use App\Context\ClientPortal\Application\Port\ListingForBooking;
use App\Context\ClientPortal\Application\Port\OnlineMotif;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetIdentity;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Service\BookingDeclineNotice;
use App\Context\ClientPortal\Application\Service\ClinicBookingRequests;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Shared\Application\Event\DomainEventPublisher;

/**
 * The new-client booking (S7d) wired on fakes: the Lilas clinic
 * (Europe/Paris) listed as LISTING, the owner account USER (+33612345678,
 * profile Sophie Martin), now 2026-11-02 10:00 UTC.
 */
final class BookingRequestWorld implements ClinicListingsInterface, ListingBookingQuotaInterface, BookingMotifsInterface, OwnerAccountsInterface, PetDirectoryInterface, PetRegistrationInterface
{
    public const string USER     = ClientPortalFakes::USER;
    public const string OTHER    = '01960000-0000-7000-8000-0000000c0002';
    public const string CLINIC   = '01960000-0000-7000-8000-0000000c1001';
    public const string CLINIC_2 = '01960000-0000-7000-8000-0000000c1002';
    public const string LISTING  = '01960000-0000-7000-8000-0000000c2001';
    public const string MOTIF    = '01960000-0000-7000-8000-0000000c3001';
    public const string VET      = '01960000-0000-7000-8000-0000000c4001';
    public const string PHONE    = '+33612345678';
    public const string CLIENT   = '01960000-0000-7000-8000-0000000c5001';
    public const string NEW_FILE = '01960000-0000-7000-8000-0000000c5099';
    public const string PET      = '01960000-0000-7000-8000-0000000c6001';
    public const string NEW_PET  = '01960000-0000-7000-8000-0000000c6099';
    public const string NOW      = '2026-11-02 10:00:00';

    public ClientPortalFakes $fakes;
    public InMemoryBookingRequests $requests;
    public InMemoryClientLinks $links;
    public InMemoryOwnerProfiles $profiles;
    public FakeOnlineBooking $booking;
    public SettableClock $clock;
    public RecordingPortalLock $locks;

    /** @var array<string, ListingForBooking> */
    public array $listings = [];

    public ?int $quotaRetryAfter = null;

    /** @var list<string> */
    public array $quotaCalls = [];

    /** @var list<OnlineMotif> */
    public array $motifs = [];

    public ?OwnerAccount $account;

    /** @var list<list<string>> */
    public array $registeredClients = [];

    /** @var list<list<string>> */
    public array $registeredPets = [];

    /** @var list<PortalPet> */
    public array $pets = [];

    public function __construct()
    {
        $this->fakes    = new ClientPortalFakes();
        $this->requests = new InMemoryBookingRequests();
        $this->links    = new InMemoryClientLinks();
        $this->profiles = new InMemoryOwnerProfiles();
        $this->booking  = new FakeOnlineBooking();
        $this->clock    = new SettableClock(self::utc(self::NOW));
        $this->locks    = new RecordingPortalLock();
        $this->account  = new OwnerAccount(self::USER, self::PHONE, null, false);

        $this->listings[self::LISTING]      = new ListingForBooking(self::LISTING, self::CLINIC, true);
        $this->motifs[]                     = new OnlineMotif(self::MOTIF, 'Première consultation', null, 30, null);
        $this->fakes->clinics[self::CLINIC] = new ClinicLabel('Clinique des Lilas', 'Lyon', 'Europe/Paris');
        $this->profiles->save(OwnerProfile::register(self::USER, 'Sophie', 'Martin', 'v1', self::utc('2026-10-01 08:00:00')));
    }

    public static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    /** A pending request of the account (or of `$userId`), created `$createdAt`. */
    public function pending(string $id, string $clinicId = self::CLINIC, string $createdAt = '2026-11-01 09:00:00', string $userId = self::USER, ?string $appointmentId = null): BookingRequest
    {
        $request = BookingRequest::request(
            BookingRequestId::fromString($id),
            $userId,
            $clinicId,
            self::LISTING,
            $appointmentId ?? $id,
            'Première consultation',
            self::utc('2026-11-05 13:30:00'),
            'Pixel',
            'cat',
            'Sophie',
            'Martin',
            self::PHONE,
            self::utc($createdAt),
        );
        $this->requests->save($request);

        return $request;
    }

    public function requestHandler(): RequestListingBookingHandler
    {
        return new RequestListingBookingHandler($this, $this->links, $this->requests, $this, $this->booking, $this, $this->profiles, $this, $this->fakes, $this->clock, $this->locks);
    }

    public function confirmHandler(): ConfirmBookingRequestHandler
    {
        return new ConfirmBookingRequestHandler($this->requests, $this->fakes, $this->clientRegistration(), $this, $this, $this->links, $this->profiles, $this->booking, $this->fakes, $this->clock, new DomainEventPublisher($this->fakes));
    }

    public function declineHandler(): DeclineBookingRequestHandler
    {
        return new DeclineBookingRequestHandler($this->requests, $this->booking, $this->notice(), $this->clock);
    }

    public function syncHandler(): SyncBookingRequestFromAppointmentHandler
    {
        return new SyncBookingRequestFromAppointmentHandler($this->requests, $this->notice(), $this->clock);
    }

    public function notice(): BookingDeclineNotice
    {
        return new BookingDeclineNotice($this->fakes, $this->fakes);
    }

    public function clinicRequests(): ClinicBookingRequests
    {
        return new ClinicBookingRequests($this->requests, $this->fakes, $this, $this->links, $this->fakes);
    }

    public function listing(string $listingId): ?ListingForBooking
    {
        return $this->listings[$listingId] ?? null;
    }

    public function consume(string $userId): void
    {
        $this->quotaCalls[] = $userId;

        if (null !== $this->quotaRetryAfter) {
            throw new ListingBookingRateLimitedException($this->quotaRetryAfter);
        }
    }

    public function onlineMotifs(string $clinicId): array
    {
        return $this->motifs;
    }

    public function accountOf(string $userId): ?OwnerAccount
    {
        return $this->account;
    }

    public function register(string $clinicId, string $ownerClientId, string $name, string $species): string
    {
        $this->registeredPets[] = [$clinicId, $ownerClientId, $name, $species];

        return self::NEW_PET;
    }

    public function clientRegistration(): ClientRegistrationInterface
    {
        return new class($this) implements ClientRegistrationInterface {
            public function __construct(private readonly BookingRequestWorld $world)
            {
            }

            public function register(string $clinicId, string $firstName, string $lastName, string $mobilePhone, ?string $actorUserId): string
            {
                $this->world->registeredClients[] = [$clinicId, $firstName, $lastName, $mobilePhone, $actorUserId ?? 'system'];

                return BookingRequestWorld::NEW_FILE;
            }
        };
    }

    public function recentPetOf(string $clinicId, string $animalId, string $ownerClientId, string $name, string $species, \DateTimeImmutable $since): ?string
    {
        return null;
    }

    public function visiblePets(string $clinicId, array $clientIds): array
    {
        return array_values(array_filter($this->pets, static fn (PortalPet $pet): bool => \in_array($pet->primaryOwnerClientId, $clientIds, true)));
    }

    public function identityOf(string $clinicId, string $animalId): ?PetIdentity
    {
        return null;
    }

    public function statesOf(string $clinicId, array $animalIds): array
    {
        return [];
    }

    public static function pet(string $id, string $clientId, string $name, string $species = 'cat'): PortalPet
    {
        return new PortalPet($id, $clientId, $name, $species, null, false, 'unknown', 'unknown', null, 'alive', null);
    }
}
