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
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointments;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 37 through the real wiring: the 15-minute scan queues one
 * DeclineExpiredPendingAppointment per expired request on `async`; each,
 * consumed as the worker would, declines its request as `expired`, and the
 * sync then tells the client once (APPOINTMENT_DECLINED). One decline that
 * fails (a request of a clinic that does not exist) holds back no other.
 */
final class ExpiredPendingRequestsPipelineTest extends KernelTestCase
{
    use Factories;

    private const string OLD_REQUEST   = '01960000-0000-7000-8000-0000000a3701';
    private const string SOON_REQUEST  = '01960000-0000-7000-8000-0000000a3702';
    private const string FRESH_REQUEST = '01960000-0000-7000-8000-0000000a3703';
    private const string BROKEN        = '01960000-0000-7000-8000-0000000a3704';
    private const string NO_CLINIC     = '01960000-0000-7000-8000-0000000c3799';

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

    public function testEveryExpiredRequestIsDeclinedAndToldOnce(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Requested 49 h ago for in 3 days: expired 1 h ago (48 h)
        $this->request(self::OLD_REQUEST, Appointments::CLINIC, $now->modify('-49 hours'), $now->modify('+3 days'));
        // Requested 1 h ago, starting in 1 h 50: expired 10 min ago (2 h before its start)
        $this->request(self::SOON_REQUEST, Appointments::CLINIC, $now->modify('-1 hour'), $now->modify('+110 minutes'));
        // Requested 1 h ago for in 3 days: untouched
        $this->request(self::FRESH_REQUEST, Appointments::CLINIC, $now->modify('-1 hour'), $now->modify('+3 days'));
        // Expired too, but its decline fails (no clinic to read the time zone of)
        $this->request(self::BROKEN, self::NO_CLINIC, $now->modify('-50 hours'), $now->modify('+3 days'));

        $queued = $this->commandBus()->dispatch(new ScanExpiredPendingAppointments());
        self::assertSame(3, $queued);
        self::assertSame(3, $this->queued('DeclineExpiredPendingAppointment'));

        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(AppointmentStatus::DECLINED, $this->statusOf(self::OLD_REQUEST));
        self::assertSame(AppointmentStatus::DECLINED, $this->statusOf(self::SOON_REQUEST));
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->statusOf(self::FRESH_REQUEST));
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->statusOf(self::BROKEN));

        // The debounced syncs, their delay elapsed (twice: a second sync finds nothing left to say)
        for ($round = 0; $round < 2; ++$round) {
            $this->connection()->executeStatement("UPDATE shared__messenger_messages SET available_at = created_at WHERE queue_name = 'default'");
            MessengerDrain::drain(self::getContainer(), ['async']);
        }

        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        foreach ([self::OLD_REQUEST, self::SOON_REQUEST] as $id) {
            $declined = $messages->findByIdempotencyKey(IdempotencyKey::fromString('appointment:' . $id . ':declined:1'));
            self::assertNotNull($declined, $id);
            self::assertSame(['APPOINTMENT_DECLINED', 'EMAIL', Appointments::OWNER], [$declined->purpose()->value, $declined->channel()->value, $declined->clientId()]);
            self::assertStringStartsWith('Demande de rendez-vous non confirmée — votre animal, ', (string) $declined->content()?->subject);
            self::assertStringContainsString("n'a pas pu être confirmée — choisissez un autre créneau dans l'app Kiveto.", (string) $declined->content()?->text);
            self::assertNull($messages->findByIdempotencyKey(IdempotencyKey::fromString('appointment:' . $id . ':declined:2')));
        }
    }

    private function request(string $id, string $clinicId, \DateTimeImmutable $createdAt, \DateTimeImmutable $startsAt): void
    {
        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);

        $appointments->save(Appointment::request(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString($clinicId),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(Appointments::ROUSSEAU)),
            timeSlot: new TimeSlot($startsAt, 30),
            reason: 'Vaccination',
            notes: null,
            createdAt: $createdAt,
            ownerId: Appointments::OWNER,
            animalId: Appointments::ANIMAL,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: min($createdAt->modify('+48 hours'), $startsAt->modify('-2 hours')),
        ));
    }

    private function statusOf(string $id): ?AppointmentStatus
    {
        $status = $this->connection()->fetchOne('SELECT status FROM scheduling__appointments WHERE id = ?', [Uuid::fromString($id)->toBinary()]);

        return \is_string($status) ? AppointmentStatus::from($status) : null;
    }

    private function queued(string $class): int
    {
        $count = $this->connection()->fetchOne("SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name = 'default' AND body LIKE ?", ['%' . $class . '%']);

        return is_numeric($count) ? (int) $count : -1;
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
