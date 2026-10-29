<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\EmailFallback;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\System\Notification\Application\Command\RequestNotification\RequestNotificationHandler;
use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\FakeRenderer;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\InMemoryVeterinarianCounts;
use App\Tests\Unit\System\Notification\Support\Messages;
use App\Tests\Unit\System\Notification\Support\SequentialUuids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class RequestNotificationHandlerTest extends TestCase
{
    private const string CLINIC = Messages::CLINIC;

    private InMemoryMessages $messages;
    private InMemoryProfiles $profiles;
    private FakeRenderer $renderer;
    private CapturingBuses $buses;
    private BufferingLogger $logger;
    private RequestNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->messages = new InMemoryMessages();
        $this->profiles = new InMemoryProfiles();
        $this->renderer = new FakeRenderer();
        $this->buses    = new CapturingBuses();
        $this->logger   = new BufferingLogger();
        $this->handler  = new RequestNotificationHandler(
            $this->messages,
            $this->profiles,
            $this->renderer,
            new SmsQuotaPolicy($this->messages, new InMemoryVeterinarianCounts(), 400, '0.079'),
            $this->buses,
            $this->buses->publisher(),
            new SequentialUuids(),
            new FrozenClock(new \DateTimeImmutable('2026-10-21T09:30:00+02:00')),
            $this->logger,
            'no-reply@kiveto.app',
            'mail.kiveto.app',
        );
        $this->profiles->save(InMemoryProfiles::montmartre(logo: 'clinic/logo.png'));
    }

    /**
     * AC 2 (request side): a clinic e-mail is sent as the clinic, from
     * Kiveto's sending sub-domain, reply-to its letterhead e-mail.
     */
    public function testAClinicMessageIsSentAsTheClinic(): void
    {
        $id = ($this->handler)($this->invoiceEmail());

        $message = $this->messages->get(OutboundMessageId::fromString($id));
        self::assertSame(DeliveryStatus::QUEUED, $message->status());
        $sender = $message->sender();
        self::assertNotNull($sender);
        self::assertSame(['Clinique Montmartre', 'clinique-montmartre@mail.kiveto.app', 'accueil@clinique-montmartre.fr'], [$sender->name, $sender->emailAddress, $sender->replyTo]);
        self::assertSame('clinic/logo.png', $message->content()?->inlineLogoKey);
        self::assertSame(Messages::CLIENT, $message->clientId());
        self::assertSame(['billing/sent/invoice/inv-1.pdf', 'FA-2026-0814.pdf', 'application/pdf', AttachmentOwner::REQUESTER], [
            $message->attachments()[0]->storageKey, $message->attachments()[0]->filename, $message->attachments()[0]->mime, $message->attachments()[0]->owner,
        ]);
        self::assertSame([], $this->profiles->locked, 'a critical message takes no lock');
        self::assertCount(1, $this->buses->external);
        self::assertInstanceOf(DispatchOutboundMessage::class, $this->buses->external[0][0]);
        self::assertSame($id, $this->buses->external[0][0]->messageId);
        $stamp = $this->buses->external[0][1][0];
        self::assertInstanceOf(TransportNamesStamp::class, $stamp);
        self::assertSame(['async_external_notification'], $stamp->getTransportNames());
        self::assertCount(1, $this->buses->eventsOf(OutboundMessageStatusChanged::class));
    }

    /**
     * AC 1: a key already used returns the existing message, nothing is sent twice.
     */
    public function testAReplayedRequestReturnsTheExistingMessage(): void
    {
        $first  = ($this->handler)($this->invoiceEmail());
        $second = ($this->handler)($this->invoiceEmail());

        self::assertSame($first, $second);
        self::assertCount(1, $this->messages->all());
        self::assertCount(1, $this->buses->external);
    }

    /**
     * AC 3: staff and test messages are sent by Kiveto, without clinic profile.
     */
    public function testAccountMessagesAreSentByKiveto(): void
    {
        $this->profiles->profiles = [];

        $id = ($this->handler)(new RequestNotification(
            purpose: 'STAFF_INVITATION',
            channel: 'EMAIL',
            recipientAddress: 'h.martin@clinique-montmartre.fr',
            recipientName: 'Hugo Martin',
            clinicId: self::CLINIC,
            clientId: null,
            subjectType: 'user',
            subjectId: 'u-1',
            idempotencyKey: 'invitation:t-1',
            parameters: ['firstName' => 'Hugo', 'inviterName' => 'Dr Claire Lambert', 'clinicName' => 'Clinique Montmartre', 'clinicCity' => 'Paris 18e', 'roleLabel' => 'Vétérinaire', 'activationUrl' => 'http://clinic.kiveto.local/invitation/x'],
        ));

        $message = $this->messages->get(OutboundMessageId::fromString($id));
        self::assertSame(['Kiveto', 'no-reply@kiveto.app', null], [$message->sender()?->name, $message->sender()?->emailAddress, $message->sender()?->replyTo]);
        self::assertNull($this->renderer->calls[0][2], 'no clinic profile is rendered');
        self::assertCount(1, $this->buses->external);
    }

    /**
     * AC 4b: a clinic without messaging profile → FAILED, logged, nothing
     * handed over; once the profile exists a new request is sent.
     */
    public function testAClinicWithoutProfileNeverSends(): void
    {
        $this->profiles->profiles = [];

        $id      = ($this->handler)($this->invoiceEmail());
        $message = $this->messages->get(OutboundMessageId::fromString($id));

        self::assertSame(DeliveryStatus::FAILED, $message->status());
        self::assertSame("Profil d'envoi de la clinique absent", $message->history()[0]->reason);
        self::assertNull($message->sender());
        self::assertSame([], $this->buses->external);
        self::assertSame([], $this->renderer->calls);
        $logs = $this->logger->cleanLogs();
        self::assertIsArray($logs[0]);
        self::assertSame('error', $logs[0][0]);
        self::assertCount(1, $this->buses->eventsOf(OutboundMessageStatusChanged::class), 'the client journal still learns about it');

        $this->profiles->save(InMemoryProfiles::montmartre());
        $retry = ($this->handler)($this->invoiceEmail('invoice:inv-1:send:d-2'));
        self::assertSame(DeliveryStatus::QUEUED, $this->messages->get(OutboundMessageId::fromString($retry))->status());
    }

    public function testAClinicSmsUsesTheClinicSender(): void
    {
        $id = ($this->handler)($this->confirmationSms());

        self::assertSame('Montmartre', $this->messages->get(OutboundMessageId::fromString($id))->sender()?->smsSender);
    }

    /**
     * AC 36: cap reached → the reminder goes by e-mail when the requester allowed it.
     */
    public function testACappedReminderFallsBackToEmail(): void
    {
        $this->reachTheCap();

        $id = ($this->handler)($this->reminderSms(fallback: true));

        $sms = $this->messages->get(OutboundMessageId::fromString($id));
        self::assertSame(DeliveryStatus::BLOCKED_CAP, $sms->status());
        self::assertSame([self::CLINIC], $this->profiles->locked, 'the cap check runs under the profile lock');

        $email = $this->messages->findByIdempotencyKey(IdempotencyKey::fromString('appointment:a-1:reminder:fallback'));
        self::assertNotNull($email);
        self::assertSame(Channel::EMAIL, $email->channel());
        self::assertSame('s.dubois@email.fr', $email->recipient()?->address);
        self::assertSame(DeliveryStatus::QUEUED, $email->status());
        self::assertSame([$email->id()->toString()], array_map(static fn (array $sent): string => $sent[0] instanceof DispatchOutboundMessage ? $sent[0]->messageId : '', $this->buses->external));
    }

    /**
     * AC 36: without a fallback the SMS is only journaled « Bloqué (plafond) ».
     */
    public function testACappedReminderWithoutFallbackIsOnlyBlocked(): void
    {
        $this->reachTheCap();

        $id = ($this->handler)($this->reminderSms(fallback: false));

        self::assertSame(DeliveryStatus::BLOCKED_CAP, $this->messages->get(OutboundMessageId::fromString($id))->status());
        self::assertSame([], $this->buses->external);
        self::assertSame('Bloqué (plafond)', $this->buses->eventsOf(OutboundMessageStatusChanged::class)[0]->statusLabel);
    }

    public function testAFallbackAlreadySentIsNotSentAgain(): void
    {
        $this->reachTheCap();
        $this->messages->save(Messages::email(id: '01960000-0000-7000-8000-0000000000fb', key: 'appointment:a-1:reminder:fallback'));

        ($this->handler)($this->reminderSms(fallback: true));

        self::assertSame([], $this->buses->external);
    }

    /**
     * AC 36: confirmations, changes and cancellations still go out.
     */
    public function testACriticalSmsIgnoresTheCap(): void
    {
        $this->reachTheCap();

        $id = ($this->handler)($this->confirmationSms());

        self::assertSame(DeliveryStatus::QUEUED, $this->messages->get(OutboundMessageId::fromString($id))->status());
        self::assertSame([], $this->profiles->locked);
    }

    /**
     * AC 36b: 10 segments left before the cap → queued segments count, the
     * rest falls back or is blocked.
     */
    public function testQueuedSegmentsCountTowardTheCap(): void
    {
        $this->profiles->save(InMemoryProfiles::montmartre(cap: 10));
        $this->sentSms(400);

        $statuses = [];
        for ($i = 1; $i <= 15; ++$i) {
            $id         = ($this->handler)($this->unpaidReminderSms('inv-' . $i));
            $statuses[] = $this->messages->get(OutboundMessageId::fromString($id))->status()->value;
        }

        self::assertSame(array_merge(array_fill(0, 10, 'QUEUED'), array_fill(0, 5, 'BLOCKED_CAP')), $statuses);
        self::assertCount(15, $this->buses->external, '10 SMS and 5 fallback e-mails');
    }

    public function testAKeyWithoutRoomForItsFallbackIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(new RequestNotification('TEST', 'EMAIL', 'a@b.fr', '', null, null, 'x', 'y', str_repeat('k', 182), ['sentAtLabel' => 'x']));
    }

    public function testAnUnknownPurposeIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('NEWSLETTER', 'EMAIL', 'a@b.fr', '', self::CLINIC, null, 'x', 'y', 'k', []));
    }

    public function testAnUnknownChannelIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('TEST', 'FAX', 'a@b.fr', '', self::CLINIC, null, 'x', 'y', 'k', []));
    }

    public function testAnSmsWithAnAttachmentIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('TEST', 'SMS', '+33612345678', '', self::CLINIC, null, 'x', 'y', 'k', ['sentAtLabel' => 'x'], [new AttachmentReference('k', 'f.pdf', 'application/pdf', 'REQUESTER')]));
    }

    public function testACriticalSmsHasNoFallback(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('TEST', 'SMS', '+33612345678', '', self::CLINIC, null, 'x', 'y', 'k', ['sentAtLabel' => 'x'], [], new EmailFallback('a@b.fr', '', ['sentAtLabel' => 'x'])));
    }

    public function testAClinicMessageNeedsTheClinic(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('INVOICE_REMINDER', 'SMS', '+33612345678', '', null, null, 'x', 'y', 'k', []));
    }

    public function testAnUnknownAttachmentOwnerIsRefused(): void
    {
        $command = $this->invoiceEmail();

        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification($command->purpose, $command->channel, $command->recipientAddress, '', self::CLINIC, null, 'x', 'y', 'k', $command->parameters, [new AttachmentReference('k', 'f.pdf', 'application/pdf', 'NOBODY')]));
    }

    public function testParametersTheTemplateDoesNotDeclareAreRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        ($this->handler)(new RequestNotification('TEST', 'EMAIL', 'a@b.fr', '', null, null, 'x', 'y', 'k', ['sentAtLabel' => 'x', 'extra' => 'y']));
    }

    private function reachTheCap(): void
    {
        $this->profiles->save(InMemoryProfiles::montmartre(cap: 0));
        $this->sentSms(400);
    }

    private function sentSms(int $segments): void
    {
        $sent = Messages::sms(id: '01960000-0000-7000-8000-0000000005e7', key: 'already-sent', segments: $segments);
        $sent->markSent('ext-sent', new \DateTimeImmutable('2026-10-05 10:00:00'));
        $this->messages->save($sent);
    }

    private function invoiceEmail(string $key = 'invoice:inv-1:send:d-1'): RequestNotification
    {
        return new RequestNotification(
            purpose: 'INVOICE_SENT',
            channel: 'EMAIL',
            recipientAddress: 't.lefebvre@orange.fr',
            recipientName: 'Thomas Lefebvre',
            clinicId: self::CLINIC,
            clientId: Messages::CLIENT,
            subjectType: 'invoice',
            subjectId: 'inv-1',
            idempotencyKey: $key,
            parameters: ['addressee' => 'Monsieur Lefebvre', 'invoiceNumber' => 'FA-2026-0814', 'invoiceDate' => '16/10/2026', 'purchaseLabel' => 'la consultation de Minou du 16 octobre 2026', 'vatLabels' => ['20 %'], 'vatAmounts' => ['14,78 €'], 'total' => '88,70 €', 'balanceDue' => '88,70 €'],
            attachments: [new AttachmentReference('billing/sent/invoice/inv-1.pdf', 'FA-2026-0814.pdf', 'application/pdf', AttachmentReference::OWNER_REQUESTER)],
        );
    }

    private function confirmationSms(): RequestNotification
    {
        return new RequestNotification('APPOINTMENT_CONFIRMATION', 'SMS', '+33612345678', 'Sophie Dubois', self::CLINIC, Messages::CLIENT, 'appointment', 'a-1', 'appointment:a-1:confirmation:1', ['animalName' => 'Milo', 'slotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => 'Dr Rousseau']);
    }

    private function reminderSms(bool $fallback): RequestNotification
    {
        return new RequestNotification(
            'APPOINTMENT_REMINDER',
            'SMS',
            '+33612345678',
            'Sophie Dubois',
            self::CLINIC,
            Messages::CLIENT,
            'appointment',
            'a-1',
            'appointment:a-1:reminder',
            ['animalName' => 'Milo', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau'],
            [],
            $fallback ? new EmailFallback('s.dubois@email.fr', 'Sophie Dubois', ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau']) : null,
        );
    }

    private function unpaidReminderSms(string $invoice): RequestNotification
    {
        return new RequestNotification(
            'INVOICE_REMINDER',
            'SMS',
            '+33612345678',
            'Claire Rey',
            self::CLINIC,
            Messages::CLIENT,
            'invoice',
            $invoice,
            'invoice:' . $invoice . ':reminder:d:SMS',
            ['invoiceNumber' => 'FA-2026-0702', 'shortDate' => '04/09', 'balanceDue' => '64,50 €'],
            [],
            new EmailFallback('claire.rey@free.fr', 'Claire Rey', ['addressee' => 'Madame Rey', 'invoiceNumber' => 'FA-2026-0702', 'invoiceDate' => '04/09/2026', 'careLabel' => 'des soins de Cookie', 'total' => '64,50 €', 'paid' => '0,00 €', 'balanceDue' => '64,50 €']),
        );
    }
}
