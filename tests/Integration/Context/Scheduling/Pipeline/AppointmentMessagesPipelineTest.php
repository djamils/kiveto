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
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * The client messages of an appointment through the real wiring: the sync
 * queued on `async`, consumed as the worker would, asks Notification for the
 * confirmation e-mail with its calendar invitation stored for the send, then
 * for the cancellation (the clinic, the client and the messaging profile are
 * real; the appointment is in 2030).
 */
final class AppointmentMessagesPipelineTest extends KernelTestCase
{
    use Factories;

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

        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);
        $appointments->save(Appointments::planned('2030-03-05 08:30', animalId: null));
    }

    public function testTheClientIsToldOfTheBookingThenOfItsCancellation(): void
    {
        $this->syncNow();

        $confirmation = $this->message('appointment:' . Appointments::ID . ':confirmation:1');
        self::assertSame(['APPOINTMENT_CONFIRMATION', 'EMAIL', Appointments::OWNER], [$confirmation->purpose()->value, $confirmation->channel()->value, $confirmation->clientId()]);
        self::assertSame('Rendez-vous confirmé — votre animal, mardi 5 mars à 9h30', $confirmation->content()?->subject);
        self::assertSame('s.dubois@email.fr', $confirmation->recipient()?->address);

        $attachments = $confirmation->attachments();
        self::assertCount(1, $attachments);
        self::assertSame(['rendez-vous.ics', 'text/calendar', 'NOTIFICATION'], [$attachments[0]->filename, $attachments[0]->mime, $attachments[0]->owner->value]);
        $ics = $this->files()->read($attachments[0]->storageKey);
        self::assertStringContainsString("METHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:" . Appointments::ID . "@kiveto.fr\r\nSEQUENCE:0\r\n", $ics);

        // A duplicate sync finds nothing left to say.
        $this->syncNow();

        $this->commandBus()->dispatch(new CancelAppointment(Appointments::ID, Appointments::CLINIC));
        $this->syncNow();

        $cancellation = $this->message('appointment:' . Appointments::ID . ':cancellation:2');
        self::assertSame('Rendez-vous annulé — votre animal, mardi 5 mars à 9h30', $cancellation->content()?->subject);
        self::assertStringContainsString("METHOD:CANCEL\r\n", $this->files()->read($cancellation->attachments()[0]->storageKey));
    }

    /** The debounced sync, its delay elapsed. */
    private function syncNow(): void
    {
        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch(new SyncAppointmentNotification(Appointments::ID));

        MessengerDrain::drain(self::getContainer(), ['async']);
    }

    private function message(string $key): OutboundMessage
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);

        return $messages->findByIdempotencyKey(IdempotencyKey::fromString($key)) ?? throw new \LogicException('No message ' . $key);
    }

    private function files(): FileStorageInterface
    {
        $files = self::getContainer()->get(FileStorageInterface::class);
        \assert($files instanceof FileStorageInterface);

        return $files;
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
