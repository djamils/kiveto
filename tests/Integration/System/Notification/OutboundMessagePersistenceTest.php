<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Domain\Message\SubjectReference;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\OutboundMessageEntity;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\UnmatchedDeliveryEventEntity;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class OutboundMessagePersistenceTest extends KernelTestCase
{
    public function testAnEmailAndItsHistoryComeBackAsTheyWentIn(): void
    {
        $messages = $this->messages();
        $message  = Messages::email(at: new \DateTimeImmutable('2026-10-16T10:00:00+02:00'));
        $messages->save($message);

        $message->markSent('<brevo-1@smtp-relay.mailin.fr>', new \DateTimeImmutable('2026-10-16 08:01:00'));
        $message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:05:00'), OutboundMessage::SOURCE_PROVIDER, 'ok');
        $message->recordComplaint(new \DateTimeImmutable('2026-10-17 09:00:00'));
        $messages->save($message);
        $this->em()->clear();

        $loaded = $messages->get($message->id());
        self::assertSame(DeliveryStatus::DELIVERED, $loaded->status());
        self::assertSame(Messages::CLINIC, $loaded->clinicId());
        self::assertSame(Messages::CLIENT, $loaded->clientId());
        self::assertSame('Thomas Lefebvre', $loaded->recipient()?->name);
        self::assertSame((array) $message->sender(), (array) $loaded->sender());
        self::assertSame((array) $message->content(), (array) $loaded->content());
        self::assertSame(array_map(static fn (object $a): array => (array) $a, $message->attachments()), array_map(static fn (object $a): array => (array) $a, $loaded->attachments()));
        self::assertSame('2026-10-16 08:00:00', $loaded->createdAt()->format('Y-m-d H:i:s'), 'stored in UTC');
        self::assertSame('2026-10-16 08:01:00', $loaded->sentAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-16 08:05:00', $loaded->statusChangedAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-17 09:00:00', $loaded->complainedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(['QUEUED', 'SENT', 'DELIVERED'], array_map(static fn ($h): string => $h->status->value, $loaded->history()));
        self::assertSame('ok', $loaded->history()[2]->reason);
        self::assertSame($loaded->id()->toString(), $messages->findByIdempotencyKey(IdempotencyKey::fromString('invoice:1:send:1'))?->id()->toString());
        self::assertSame($loaded->id()->toString(), $messages->findByExternalId(Channel::EMAIL, '<brevo-1@smtp-relay.mailin.fr>')?->id()->toString());
        self::assertNull($messages->findByExternalId(Channel::SMS, '<brevo-1@smtp-relay.mailin.fr>'));
        self::assertNull($messages->findByIdempotencyKey(IdempotencyKey::fromString('unknown')));

        $row = $this->em()->find(OutboundMessageEntity::class, Uuid::fromString($message->id()->toString()));
        self::assertNotNull($row);
        self::assertSame(['CRITICAL', 0, 2], [$row->getCriticality(), $row->getSegments(), $row->getVersion()]);
    }

    public function testAFailedAndAPurgedMessageComeBack(): void
    {
        $messages = $this->messages();
        $failed   = OutboundMessage::failedBeforeHandOver(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000f01'),
            null,
            null,
            MessagePurpose::PASSWORD_RESET,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 'dr.rousseau@clinique-montmartre.fr', ''),
            SubjectReference::of('user', 'u1'),
            IdempotencyKey::fromString('password-reset:t1'),
            'Rendu impossible',
            new \DateTimeImmutable('2026-10-16 08:14:00'),
        );
        $messages->save($failed);

        $sms = Messages::sms(segments: 2);
        $sms->purgeContent(new \DateTimeImmutable('2028-10-22 03:47:00'));
        $messages->save($sms);
        $this->em()->clear();

        $loadedFailed = $messages->get($failed->id());
        self::assertSame(DeliveryStatus::FAILED, $loadedFailed->status());
        self::assertNull($loadedFailed->sender());
        self::assertNull($loadedFailed->content());
        self::assertNull($loadedFailed->clinicId());
        self::assertSame('', $loadedFailed->recipient()?->name);

        $loadedSms = $messages->get($sms->id());
        self::assertNull($loadedSms->recipient());
        self::assertSame(2, $loadedSms->segments());
        self::assertSame(SmsEncoding::GSM_7, $loadedSms->content()?->encoding);
        self::assertSame('2028-10-22 03:47:00', $loadedSms->contentPurgedAt()?->format('Y-m-d H:i:s'));
    }

    public function testAnUnknownMessageIsNotFound(): void
    {
        $this->expectException(OutboundMessageNotFoundException::class);

        $this->messages()->get(OutboundMessageId::fromString('01960000-0000-7000-8000-0000000000aa'));
    }

    public function testASecondMessageWithTheSameKeyIsRetried(): void
    {
        $messages = $this->messages();
        $messages->save(Messages::email());

        $this->expectException(RecoverableMessageHandlingException::class);

        $messages->save(Messages::email(id: '01960000-0000-7000-8000-000000000e02'));
    }

    public function testAConflictOnAnExistingMessageIsNotRetried(): void
    {
        $messages = $this->messages();
        $messages->save(Messages::email());
        $messages->save(Messages::email(id: '01960000-0000-7000-8000-000000000e02', key: 'other'));

        $this->expectException(UniqueConstraintViolationException::class);

        // The stored second message rewritten with the first one's key.
        $messages->save(Messages::email(id: '01960000-0000-7000-8000-000000000e02'));
    }

    /**
     * AC 33b: blocked, queued and failed SMS are never billed; only SMS handed
     * to the provider in the month count.
     */
    public function testOnlySmsHandedToTheProviderAreBilled(): void
    {
        $messages = $this->messages();
        $clinic   = Messages::CLINIC;
        $other    = '01960000-0000-7000-8000-00000000c22c';
        $id       = 0;
        $next     = static function () use (&$id): string {
            return \sprintf('01960000-0000-7000-8000-%012d', ++$id);
        };

        $sent = Messages::sms(id: $next(), key: 'k-sent', segments: 2);
        $sent->markSent('sms-1', new \DateTimeImmutable('2026-10-31T23:30:00+01:00'));
        $messages->save($sent);

        $nextMonth = Messages::sms(id: $next(), key: 'k-next', segments: 5);
        $nextMonth->markSent('sms-2', new \DateTimeImmutable('2026-11-01T00:30:00+01:00'));
        $messages->save($nextMonth);

        $delivered = Messages::sms(id: $next(), key: 'k-delivered', segments: 1);
        $delivered->markSent('sms-3', new \DateTimeImmutable('2026-10-01T00:00:00+02:00'));
        $delivered->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-01 00:01:00'), OutboundMessage::SOURCE_PROVIDER);
        $messages->save($delivered);

        $messages->save(Messages::sms(id: $next(), key: 'k-queued', segments: 3));
        $messages->save(Messages::sms(id: $next(), key: 'k-queued-other', clinicId: $other, segments: 7));

        $failed = Messages::sms(id: $next(), key: 'k-failed', segments: 4);
        $failed->markFailed('HTTP 400', new \DateTimeImmutable('2026-10-10'));
        $messages->save($failed);

        for ($i = 0; $i < 10; ++$i) {
            $messages->save(OutboundMessage::blockedByCap(
                OutboundMessageId::fromString($next()),
                $clinic,
                null,
                MessagePurpose::INVOICE_REMINDER,
                Recipient::for(Channel::SMS, '+33612345678', ''),
                SenderIdentity::kiveto('no-reply@kiveto.app'),
                SubjectReference::of('invoice', 'i' . $i),
                IdempotencyKey::fromString('k-blocked-' . $i),
                RenderedContent::sms('x', SmsEncoding::GSM_7, 1),
                new \DateTimeImmutable('2026-10-10'),
            ));
        }

        $sentEmail = Messages::email(id: $next(), key: 'k-email');
        $sentEmail->markSent('mail-1', new \DateTimeImmutable('2026-10-10'));
        $messages->save($sentEmail);

        $october = [new \DateTimeImmutable('2026-10-01T00:00:00+02:00'), new \DateTimeImmutable('2026-11-01T00:00:00+01:00')];
        self::assertSame(3, $messages->sumBilledSegments($clinic, ...$october));
        self::assertSame(5, $messages->sumBilledSegments($clinic, new \DateTimeImmutable('2026-11-01T00:00:00+01:00'), new \DateTimeImmutable('2026-12-01T00:00:00+01:00')));
        self::assertSame(0, $messages->sumBilledSegments($other, ...$october));
        self::assertSame(3, $messages->sumQueuedSegments($clinic));
        self::assertSame(7, $messages->sumQueuedSegments($other));
    }

    public function testAnUnmatchedDeliveryEventIsKept(): void
    {
        $event = new UnmatchedDeliveryEventEntity(Uuid::fromString('01960000-0000-7000-8000-0000000000ee'), 'SMS', 'sms-9', ['event' => 'delivered'], new \DateTimeImmutable('2026-10-16 08:00:00'));
        $this->em()->persist($event);
        $this->em()->flush();
        $this->em()->clear();

        $loaded = $this->em()->find(UnmatchedDeliveryEventEntity::class, Uuid::fromString('01960000-0000-7000-8000-0000000000ee'));
        self::assertNotNull($loaded);
        self::assertSame('01960000-0000-7000-8000-0000000000ee', $loaded->getId()->toRfc4122());
        self::assertSame(['SMS', 'sms-9', ['event' => 'delivered']], [$loaded->getChannel(), $loaded->getExternalId(), $loaded->getPayload()]);
        self::assertSame('2026-10-16 08:00:00', $loaded->getReceivedAt()->format('Y-m-d H:i:s'));
    }

    private function messages(): OutboundMessageRepositoryInterface
    {
        $service = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($service instanceof OutboundMessageRepositoryInterface);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
