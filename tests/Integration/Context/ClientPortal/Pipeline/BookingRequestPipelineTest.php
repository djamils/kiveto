<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Pipeline;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequest;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBooking;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment\DeclineExpiredPendingAppointment;
use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Event\PendingAppointmentDeclinedIntegrationEvent;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\DisablePortalUser\DisablePortalUser;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\ClientPortal\Support\SettableClock;
use App\Tests\Unit\Context\Scheduling\Support\FakeAvailabilitySources;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * S7d through the real buses (AC 80, 81, 90, 268, 269): a new client's
 * request through the directory, then confirmed, declined, expired,
 * cancelled from the agenda, delivered twice, or erased with the account.
 * The clinic's hours, the practitioner's eligibility and blocks are doubles.
 * Now: Monday 13 April 2026, 08:00 in Paris.
 */
final class BookingRequestPipelineTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000c4801';
    private const string LISTING = '01960000-0000-7000-8000-0000000c4802';
    private const string VET     = '01960000-0000-7000-8000-0000000d4801';
    private const string MOTIF   = '01960000-0000-7000-8000-0000000b4801';
    private const string PHONE   = '+33615004802';

    private CommandBusInterface $bus;
    private Connection $connection;
    private SettableClock $clock;
    private string $userId;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $sources   = new FakeAvailabilitySources();
        $weekday   = [['opensAt' => '08:30', 'closesAt' => '12:30']];

        $sources->clinic      = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [1 => $weekday, 2 => $weekday, 3 => $weekday], []);
        $sources->eligible    = [self::VET];
        $sources->occurrences = [new PlanningBlockOccurrence('2026-04-15', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0)];
        $this->clock          = new SettableClock(new \DateTimeImmutable('2026-04-13 06:00', new \DateTimeZone('UTC')));

        $container->set(ClinicOpeningHoursInterface::class, $sources);
        $container->set(MembershipEligibilityCheckerInterface::class, $sources);
        $container->set(PlanningBlockOccurrencesReaderInterface::class, $sources);
        $container->set(ClinicTimezoneResolverInterface::class, new ParisTimezone());
        $container->set(ClockInterface::class, $this->clock);

        $this->bus        = self::service(CommandBusInterface::class);
        $this->connection = self::service(Connection::class);

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->create(['timeZone' => 'Europe/Paris']);
        $this->userId = PortalUserFactory::new()->withPhone(self::PHONE)->create()->getId()->toRfc4122();
        self::service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::register($this->userId, 'Sophie', 'Martin', 'v1', new \DateTimeImmutable('2026-04-01')));

        $profile = new ListingProfile('Clinique des Lilas', ListingAddress::fromParts('18 rue Lepic', '75018', 'Paris'), null, 'Europe/Paris', [], true);
        self::service(ClinicListingRepositoryInterface::class)->save(ClinicListing::reconstitute(ClinicListingId::fromString(self::LISTING), self::CLINIC, $profile, null, $profile->address()?->hash(), new \DateTimeImmutable('2026-04-01')));
        $settings = ListingSettings::defaults(self::CLINIC);
        $settings->update(true, true, []);
        self::service(ListingSettingsRepositoryInterface::class)->save($settings);

        self::service(OnlineBookingSettingsRepositoryInterface::class)->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), true, 15, 120, 24, 60, 24, true));
        self::service(BookingMotifRepositoryInterface::class)->save(BookingMotif::create(BookingMotifId::fromString(self::MOTIF), ClinicId::fromString(self::CLINIC), 'Première consultation', null, 30, true, ConfirmationMode::AUTO, [PlanningBlockType::CONSULTATION], [UserId::fromString(self::VET)], null, true, 0));
    }

    public function testConfirmingCreatesTheFileTheAnimalAndTheLinkAndTheSiblingFollows(): void
    {
        // AC 80, AC 268
        $first  = $this->request('2026-04-15T10:00:00+02:00');
        $second = $this->secondRequestAtTheClinic();

        $this->bus->dispatch(new ConfirmBookingRequest(self::CLINIC, $first, ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null, self::VET, 'Dr Vet'));
        MessengerDrain::drain(self::getContainer(), ['async']);

        $request = $this->stored($first);
        self::assertSame(BookingRequestStatus::CONFIRMED, $request->status());
        $clientId = $request->clientId();
        self::assertNotNull($clientId);
        self::assertSame(['Sophie', 'Martin'], array_values($this->connection->fetchAssociative('SELECT first_name, last_name FROM client__clients WHERE id = ?', [Uuid::fromString($clientId)->toBinary()]) ?: []));
        $appointment = $this->connection->fetchAssociative('SELECT status, owner_id FROM scheduling__appointments WHERE id = ?', [Uuid::fromString($request->appointmentId())->toBinary()]) ?: [];
        self::assertSame('PLANNED', $appointment['status'] ?? null);
        self::assertSame(Uuid::fromString($clientId)->toBinary(), $appointment['owner_id'] ?? null);
        self::assertSame(['confirmed'], $this->connection->fetchFirstColumn('SELECT status FROM client_portal__client_links WHERE user_id = ?', [Uuid::fromString($this->userId)->toBinary()]));
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM animal__animals WHERE clinic_id = ? AND name = ?', [Uuid::fromString(self::CLINIC)->toBinary(), 'Pixel']));

        // The second request carries the same file: confirming it creates no second client
        self::assertSame($clientId, $this->stored($second)->clientId());
        $this->bus->dispatch(new ConfirmBookingRequest(self::CLINIC, $second, ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null, self::VET, 'Dr Vet'));
        self::assertSame($clientId, $this->stored($second)->clientId());
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM client__clients WHERE clinic_id = ?', [Uuid::fromString(self::CLINIC)->toBinary()]));
        self::assertSame([], $this->declineMessages());
    }

    public function testADeclineSendsOneSmsEvenWhenItsEventIsDeliveredTwice(): void
    {
        // AC 81
        $id = $this->request('2026-04-15T10:00:00+02:00');

        $this->bus->dispatch(new DeclineBookingRequest(self::CLINIC, $id, self::VET, 'Dr Vet'));
        MessengerDrain::drain(self::getContainer(), ['async']);
        $this->deliverDeclinedAgain($id, 'declined');

        self::assertSame([BookingRequestStatus::DECLINED, DeclineReason::DECLINED], [$this->stored($id)->status(), $this->stored($id)->declineReason()]);
        self::assertSame(['DECLINED'], $this->appointmentStatuses());
        self::assertSame([[self::PHONE, 'portal-booking-declined:' . $id]], $this->declineMessages());
    }

    public function testAnExpiredRequestIsDeclinedAndToldOnce(): void
    {
        $id = $this->request('2026-04-15T10:00:00+02:00');
        // The expiry: min(created + 48 h, start − 2 h) = 15 April 06:00 UTC
        $this->clock->now = new \DateTimeImmutable('2026-04-15 06:00', new \DateTimeZone('UTC'));

        $this->queue(new DeclineExpiredPendingAppointment($this->stored($id)->appointmentId()));
        MessengerDrain::drain(self::getContainer(), ['async']);
        $this->deliverDeclinedAgain($id, 'expired');

        self::assertSame(DeclineReason::EXPIRED, $this->stored($id)->declineReason());
        self::assertSame([[self::PHONE, 'portal-booking-declined:' . $id]], $this->declineMessages());
    }

    public function testAClinicCancelFromTheAgendaDeclinesTheRequest(): void
    {
        $id = $this->request('2026-04-15T10:00:00+02:00');

        $this->bus->dispatch(new CancelAppointment($this->stored($id)->appointmentId(), self::CLINIC));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(DeclineReason::CANCELLED, $this->stored($id)->declineReason());
        self::assertCount(1, $this->declineMessages());
    }

    public function testTheAccountDeletionDeclinesWithoutMessageFreesTheSlotAndErasesTheSnapshots(): void
    {
        // AC 90, AC 269: a request confirmed (its appointment stays) and one pending
        $confirmed = $this->request('2026-04-15T10:00:00+02:00');
        $id        = $this->secondRequestAtTheClinic();
        $this->bus->dispatch(new ConfirmBookingRequest(self::CLINIC, $confirmed, ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null, self::VET, 'Dr Vet'));

        // As RequestAccountDeletion does: the account disabled, the erasure queued
        $this->bus->dispatch(new DisablePortalUser($this->userId));
        $this->queue(new DeletePortalAccount($this->userId));
        MessengerDrain::drain(self::getContainer(), ['async']);

        $request = $this->stored($id);
        self::assertSame([BookingRequestStatus::DECLINED, DeclineReason::ACCOUNT_DELETED], [$request->status(), $request->declineReason()]);
        self::assertSame([null, null, null, null, null], [$request->requesterFirstName(), $request->requesterLastName(), $request->requesterPhone(), $request->newPetName(), $request->newPetSpecies()]);
        self::assertSame(['CANCELLED', 'PLANNED'], $this->appointmentStatuses());
        self::assertSame([BookingRequestStatus::CONFIRMED, null], [$this->stored($confirmed)->status(), $this->stored($confirmed)->requesterPhone()]);
        self::assertSame([], $this->declineMessages());
    }

    private function request(string $startsAt): string
    {
        $id = $this->bus->dispatch(new RequestListingBooking($this->userId, self::LISTING, 'Pixel', 'cat', self::MOTIF, self::VET, new \DateTimeImmutable($startsAt)));
        \assert(\is_string($id));

        return $id;
    }

    /** A second pending request of the account at the clinic, written directly (the limit allows one through the command). */
    private function secondRequestAtTheClinic(): string
    {
        $id            = Uuid::v7()->toRfc4122();
        $appointmentId = $this->bus->dispatch(new BookOnlineAppointment(self::CLINIC, self::MOTIF, self::VET, new \DateTimeImmutable('2026-04-15T11:00:00+02:00'), null, null, newClient: true));
        \assert(\is_string($appointmentId));
        self::service(BookingRequestRepositoryInterface::class)->save(BookingRequest::request(
            BookingRequestId::fromString($id),
            $this->userId,
            self::CLINIC,
            self::LISTING,
            $appointmentId,
            'Première consultation',
            new \DateTimeImmutable('2026-04-15 09:00', new \DateTimeZone('UTC')),
            'Pixel',
            'cat',
            'Sophie',
            'Martin',
            self::PHONE,
            new \DateTimeImmutable('2026-04-13 06:00', new \DateTimeZone('UTC')),
        ));

        return $id;
    }

    /** A command routed to `async`, queued then handled by the worker. */
    private function queue(object $command): void
    {
        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch($command);
    }

    private function deliverDeclinedAgain(string $requestId, string $reason): void
    {
        self::service(IntegrationEventBusInterface::class)->publish([], new PendingAppointmentDeclinedIntegrationEvent($this->stored($requestId)->appointmentId(), self::CLINIC, null, null, '2026-04-15 08:00:00', $reason));
        MessengerDrain::drain(self::getContainer(), ['async']);
    }

    private function stored(string $id): BookingRequest
    {
        return self::service(BookingRequestRepositoryInterface::class)->findInClinic(self::CLINIC, $id) ?? throw new \LogicException('Unknown request.');
    }

    /**
     * @return list<string>
     */
    private function appointmentStatuses(): array
    {
        return array_map(static fn (mixed $status): string => \is_string($status) ? $status : '', $this->connection->fetchFirstColumn('SELECT status FROM scheduling__appointments WHERE clinic_id = ? ORDER BY status', [Uuid::fromString(self::CLINIC)->toBinary()]));
    }

    /**
     * @return list<list<string>> recipient, idempotency key
     */
    private function declineMessages(): array
    {
        return array_map(
            static fn (array $row): array => [\is_string($row['recipient_address']) ? $row['recipient_address'] : '', \is_string($row['idempotency_key']) ? $row['idempotency_key'] : ''],
            $this->connection->fetchAllAssociative("SELECT recipient_address, idempotency_key FROM notification__outbound_messages WHERE purpose = 'PORTAL_BOOKING_DECLINED'"),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
