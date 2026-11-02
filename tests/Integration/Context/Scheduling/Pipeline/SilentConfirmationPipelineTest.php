<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Pipeline;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\ValueObject\AppointmentId as ConsultationAppointmentId;
use App\Context\Consultation\Domain\ValueObject\UserId as ConsultationUserId;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently\ConfirmPendingAppointmentSilently;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 140 through the real wiring: the client is on site, so a pending
 * request is confirmed silently by each entry point — a consultation
 * started from it (Consultation's coordinator), the check-in, the
 * completion. The portal booking is announced confirmed
 * (`PendingAppointmentConfirmedIntegrationEvent`, outbox), the sync marks
 * the slot as told and no message is ever requested from Notification.
 */
final class SilentConfirmationPipelineTest extends KernelTestCase
{
    use Factories;

    private const string ADMISSION = '01960000-0000-7000-8000-0000000b0140';

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(Appointments::CLINIC)->create(['timeZone' => 'Europe/Paris', 'name' => 'Clinique Montmartre']);
        $this->commandBus()->dispatch(new UpsertClinicMessagingProfile(Appointments::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', null));

        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString(Appointments::OWNER),
            ClientClinicId::fromString(Appointments::CLINIC),
            new ClientIdentity('Sophie', 'Dubois', Civility::MADAM),
            [ContactMethod::email(EmailAddress::fromString('s.dubois@email.fr'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));
    }

    /**
     * @return iterable<string, array{string, AppointmentStatus}>
     */
    public static function provideAPendingPortalRequestIsConfirmedSilentlyAndAnnouncedCases(): iterable
    {
        yield 'a consultation started from it' => ['consultation', AppointmentStatus::PLANNED];
        yield 'the check-in' => ['check-in', AppointmentStatus::COMPLETED];
        yield 'the completion' => ['completion', AppointmentStatus::COMPLETED];
        yield 'the command itself' => ['command', AppointmentStatus::PLANNED];
    }

    #[DataProvider('provideAPendingPortalRequestIsConfirmedSilentlyAndAnnouncedCases')]
    public function testAPendingPortalRequestIsConfirmedSilentlyAndAnnounced(string $entryPoint, AppointmentStatus $status): void
    {
        $this->saveRequest(AppointmentSource::PORTAL);

        $this->enter($entryPoint);

        $appointment = $this->appointments()->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([$status, true], [$appointment?->status(), $appointment?->confirmedSilently()]);
        self::assertSame(1, $this->queued('PendingAppointmentConfirmedIntegrationEvent'));

        $this->syncNow();

        $this->assertNoMessageWasRequested(slotTold: AppointmentStatus::PLANNED === $status);
    }

    public function testAClinicRequestIsConfirmedSilentlyWithoutIntegrationEvent(): void
    {
        $this->saveRequest(AppointmentSource::CLINIC);

        $this->enter('command');

        self::assertSame(AppointmentStatus::PLANNED, $this->appointments()->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame(0, $this->queued('PendingAppointmentConfirmedIntegrationEvent'));

        $this->syncNow();

        $this->assertNoMessageWasRequested();
    }

    public function testAnAppointmentAlreadyPlannedIsLeftAsItIs(): void
    {
        $this->appointments()->save(Appointments::planned('2030-03-05 08:30'));

        $this->enter('consultation');

        $appointment = $this->appointments()->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, false], [$appointment?->status(), $appointment?->confirmedSilently()]);
        self::assertSame(0, $this->queued('PendingAppointmentConfirmedIntegrationEvent'));
        self::assertSame(0, $this->queued('SyncAppointmentNotification'));
    }

    private function enter(string $entryPoint): void
    {
        match ($entryPoint) {
            'consultation' => $this->coordinator()->ensureAppointmentInService(
                ConsultationAppointmentId::fromString(Appointments::ID),
                ConsultationUserId::fromString(Appointments::ROUSSEAU),
            ),
            'check-in'   => $this->commandBus()->dispatch(new CheckInAppointment(Appointments::CLINIC, Appointments::ID, self::ADMISSION)),
            'completion' => $this->commandBus()->dispatch(new CompleteAppointment(Appointments::ID)),
            default      => $this->commandBus()->dispatch(new ConfirmPendingAppointmentSilently(Appointments::ID, Appointments::CLINIC)),
        };
    }

    private function saveRequest(AppointmentSource $source): void
    {
        $this->appointments()->save(Appointments::pending('2030-03-05 08:30', '2030-03-04 08:00', $source));
    }

    private function assertNoMessageWasRequested(bool $slotTold = true): void
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        self::assertNull($messages->findByIdempotencyKey(IdempotencyKey::fromString('appointment:' . Appointments::ID . ':confirmation:1')));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM notification__outbound_messages WHERE idempotency_key LIKE ?', 'appointment:' . Appointments::ID . ':%'));

        if (!$slotTold) {
            // Completed at once: nothing is left to tell
            return;
        }

        // The notification state counts the slot as told
        self::assertIsString($this->connection()->fetchOne(
            'SELECT confirmed_at FROM scheduling__appointment_notifications WHERE appointment_id = ?',
            [Uuid::fromString(Appointments::ID)->toBinary()],
        ));
    }

    /** The debounced sync, its delay elapsed. */
    private function syncNow(): void
    {
        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch(new SyncAppointmentNotification(Appointments::ID));

        $this->connection()->executeStatement("UPDATE shared__messenger_messages SET available_at = created_at WHERE queue_name = 'default'");
        MessengerDrain::drain(self::getContainer(), ['async']);
    }

    private function queued(string $message): int
    {
        return $this->countRows('SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE ?', '%' . $message . '%');
    }

    private function countRows(string $sql, string $parameter): int
    {
        $count = $this->connection()->fetchOne($sql, [$parameter]);

        return is_numeric($count) ? (int) $count : -1;
    }

    private function appointments(): AppointmentRepositoryInterface
    {
        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);

        return $appointments;
    }

    private function coordinator(): SchedulingServiceCoordinatorInterface
    {
        $coordinator = self::getContainer()->get(SchedulingServiceCoordinatorInterface::class);
        \assert($coordinator instanceof SchedulingServiceCoordinatorInterface);

        return $coordinator;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
