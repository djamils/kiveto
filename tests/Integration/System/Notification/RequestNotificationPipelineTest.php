<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Support\RecordingExternalApiCommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RequestNotification through the real command bus: the clinic profile and
 * count pushed by Clinic, the rendered message saved, its dispatch queued for
 * the external worker and its status published to the outbox.
 */
final class RequestNotificationPipelineTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c0b1';

    public function testAClinicSmsIsRenderedSavedAndQueued(): void
    {
        $bus = $this->commandBus();
        $bus->dispatch(new UpsertClinicMessagingProfile(self::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', null));
        $bus->dispatch(new RecordVeterinarianCount(self::CLINIC, '2026-10-01', 3));

        $id = $bus->dispatch(new RequestNotification(
            purpose: 'APPOINTMENT_REMINDER',
            channel: 'SMS',
            recipientAddress: '+33612345678',
            recipientName: 'Sophie Dubois',
            clinicId: self::CLINIC,
            clientId: null,
            subjectType: 'appointment',
            subjectId: 'a-1',
            idempotencyKey: 'appointment:a-1:reminder',
            parameters: ['animalName' => 'Chloë', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau'],
        ));
        \assert(\is_string($id));

        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $message = $messages->get(OutboundMessageId::fromString($id));

        self::assertSame(DeliveryStatus::QUEUED, $message->status());
        self::assertSame('Clinique Montmartre : rappel du RDV de Chloe demain à 9h30 avec le Dr Rousseau. En cas d\'absence, merci de nous prévenir au 01 42 64 18 00', $message->content()?->smsText);
        self::assertSame(1, $message->segments());
        self::assertSame('Montmartre', $message->sender()?->smsSender);

        $recorder = self::getContainer()->get(RecordingExternalApiCommandBus::class);
        \assert($recorder instanceof RecordingExternalApiCommandBus);
        $dispatches = $recorder->recorded(DispatchOutboundMessage::class);
        self::assertCount(1, $dispatches);

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $queues = $connection->fetchFirstColumn('SELECT queue_name FROM shared__messenger_messages WHERE body LIKE ?', ['%' . $id . '%']);
        self::assertContains('external_notification', $queues, 'the dispatch waits on its own queue');
        self::assertNotContains('external', $queues, 'and only there');
        self::assertContains('default', $queues, 'the status change is in the outbox');
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM notification__veterinarian_counts WHERE clinic_id = ?', [Uuid::fromString(self::CLINIC)->toBinary()]));

        self::assertSame($id, $bus->dispatch(new RequestNotification('APPOINTMENT_REMINDER', 'SMS', '+33612345678', 'Sophie Dubois', self::CLINIC, null, 'appointment', 'a-1', 'appointment:a-1:reminder', [])), 'replayed: same message, parameters not even checked');
    }

    /**
     * The external worker hands the queued message to the transport of
     * MAILER_DSN (null:// here) and records it SENT.
     */
    public function testTheExternalWorkerSendsTheQueuedMessage(): void
    {
        $id = $this->commandBus()->dispatch(new RequestNotification('TEST', 'EMAIL', 'claire@kiveto.app', 'Claire', self::CLINIC, null, 'clinic', self::CLINIC, 'test:pipeline:1', ['sentAtLabel' => '26/10/2026 à 10:42']));
        \assert(\is_string($id));

        MessengerDrain::drain(self::getContainer(), ['async_external_notification']);

        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $message = $messages->get(OutboundMessageId::fromString($id));
        self::assertSame(DeliveryStatus::SENT, $message->status());
        self::assertNotNull($message->externalMessageId());
    }

    /**
     * NOTIFICATION_DELIVERY=simulator: e-mail through MAILER_DSN, SMS kept
     * local, and the delivery webhook faked on the `async` transport.
     */
    public function testTheSimulatorPlaysTheDeliveryWebhooks(): void
    {
        $bus     = $this->commandBus();
        $request = static fn (string $channel, string $to, string $key): RequestNotification => new RequestNotification('TEST', $channel, $to, '', self::CLINIC, null, 'clinic', self::CLINIC, $key, ['sentAtLabel' => '26/10/2026 à 10:42']);
        $ids     = [
            'DELIVERED' => $bus->dispatch($request('EMAIL', 'claire@kiveto.app', 'test:sim:1')),
            'BOUNCED'   => $bus->dispatch($request('EMAIL', 'bounce@kiveto.app', 'test:sim:2')),
            'REJECTED'  => $bus->dispatch($request('SMS', '+33612340000', 'test:sim:3')),
            'SMS'       => $bus->dispatch($request('SMS', '+33612345678', 'test:sim:4')),
        ];

        MessengerDrain::drain(self::getContainer(), ['async_external_notification', 'async']);

        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $statuses = array_map(static function (mixed $id) use ($messages): string {
            \assert(\is_string($id));

            return $messages->get(OutboundMessageId::fromString($id))->status()->value;
        }, $ids);

        self::assertSame(['DELIVERED' => 'DELIVERED', 'BOUNCED' => 'BOUNCED', 'REJECTED' => 'REJECTED', 'SMS' => 'DELIVERED'], $statuses);
        \assert(\is_string($ids['SMS']));
        self::assertStringStartsWith('sim-sms-', (string) $messages->get(OutboundMessageId::fromString($ids['SMS']))->externalMessageId());
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
