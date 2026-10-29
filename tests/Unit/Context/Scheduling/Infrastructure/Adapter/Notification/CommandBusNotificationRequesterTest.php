<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Notification;

use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\StoredAttachment;
use App\Context\Scheduling\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Context\Scheduling\Infrastructure\Adapter\Notification\FileStorageAttachmentStore;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;

final class CommandBusNotificationRequesterTest extends TestCase
{
    public function testAnAppointmentMessageBecomesANotificationRequest(): void
    {
        $bus = $this->bus();

        new CommandBusNotificationRequester($bus)->request(new AppointmentMessage(
            purpose: AppointmentMessage::CONFIRMATION,
            channel: 'EMAIL',
            recipientAddress: 's.dubois@email.fr',
            recipientName: 'Sophie Dubois',
            clinicId: 'clinic-1',
            clientId: 'client-1',
            appointmentId: 'appointment-1',
            idempotencyKey: 'appointment:appointment-1:confirmation:1',
            parameters: ['animalName' => 'Milo'],
            attachment: new StoredAttachment('notification/attachments/x/rendez-vous.ics', 'rendez-vous.ics', 'text/calendar'),
        ));
        new CommandBusNotificationRequester($bus)->request(new AppointmentMessage(
            purpose: AppointmentMessage::REMINDER,
            channel: 'SMS',
            recipientAddress: '+33612345678',
            recipientName: 'Sophie Dubois',
            clinicId: 'clinic-1',
            clientId: 'client-1',
            appointmentId: 'appointment-1',
            idempotencyKey: 'appointment:appointment-1:reminder',
            parameters: ['animalName' => 'Milo'],
            fallbackAddress: 's.dubois@email.fr',
            fallbackParameters: ['addressee' => 'Madame Dubois'],
        ));

        [$confirmation, $reminder] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $confirmation);
        self::assertSame(['APPOINTMENT_CONFIRMATION', 'EMAIL', 's.dubois@email.fr', 'Sophie Dubois', 'clinic-1', 'client-1', 'appointment', 'appointment-1', 'appointment:appointment-1:confirmation:1', ['animalName' => 'Milo']], [
            $confirmation->purpose, $confirmation->channel, $confirmation->recipientAddress, $confirmation->recipientName, $confirmation->clinicId, $confirmation->clientId,
            $confirmation->subjectType, $confirmation->subjectId, $confirmation->idempotencyKey, $confirmation->parameters,
        ]);
        self::assertCount(1, $confirmation->attachments);
        self::assertSame(['notification/attachments/x/rendez-vous.ics', 'rendez-vous.ics', 'text/calendar', AttachmentReference::OWNER_NOTIFICATION], [
            $confirmation->attachments[0]->storageKey, $confirmation->attachments[0]->filename, $confirmation->attachments[0]->mime, $confirmation->attachments[0]->owner,
        ]);
        self::assertNull($confirmation->fallback);

        self::assertInstanceOf(RequestNotification::class, $reminder);
        self::assertSame([], $reminder->attachments);
        self::assertSame(['s.dubois@email.fr', 'Sophie Dubois', ['addressee' => 'Madame Dubois']], [$reminder->fallback?->address, $reminder->fallback?->name, $reminder->fallback?->parameters]);
    }

    public function testAttachmentsAreStoredUnderTheHashOfTheirMessageKey(): void
    {
        $files = new InMemoryFiles();

        $stored = new FileStorageAttachmentStore($files)->store('appointment:a-1:confirmation:1', 'rendez-vous.ics', 'text/calendar', 'BEGIN:VCALENDAR');

        $key = 'notification/attachments/' . hash('sha256', 'appointment:a-1:confirmation:1') . '/rendez-vous.ics';
        self::assertSame([$key, 'rendez-vous.ics', 'text/calendar'], [$stored->storageKey, $stored->filename, $stored->mime]);
        self::assertSame([$key => 'BEGIN:VCALENDAR'], $files->files);
    }

    /**
     * @return CommandBusInterface&object{commands: list<object>}
     */
    private function bus(): CommandBusInterface
    {
        return new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 'message-id';
            }
        };
    }
}
