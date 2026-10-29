<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFilesHandler;
use App\System\Notification\Application\Command\PurgeMessageContentBatch\PurgeMessageContentBatch;
use App\System\Notification\Application\Command\PurgeMessageContentBatch\PurgeMessageContentBatchHandler;
use App\System\Notification\Application\Command\PurgeMessageContents\PurgeMessageContents;
use App\System\Notification\Application\Command\PurgeMessageContents\PurgeMessageContentsHandler;
use App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents\PurgeUnmatchedDeliveryEvents;
use App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents\PurgeUnmatchedDeliveryEventsHandler;
use App\System\Notification\Application\Command\RematchDeliveryEvents\RematchDeliveryEvents;
use App\System\Notification\Application\Command\RematchDeliveryEvents\RematchDeliveryEventsHandler;
use App\System\Notification\Application\Command\ReplayDeliveryEvents\ReplayDeliveryEvents;
use App\System\Notification\Application\Command\ReplayDeliveryEvents\ReplayDeliveryEventsHandler;
use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SubjectReference;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\CapturingMessageBus;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryUnmatchedEvents;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class RetentionHandlersTest extends TestCase
{
    private const string NOW = '2028-10-21T03:47:00+02:00';

    /**
     * AC 40c: bodies, recipient and Notification-owned files of messages sent
     * more than 24 months ago are deleted; metadata and Billing's PDF remain.
     */
    public function testOldMessageContentsArePurged(): void
    {
        $messages = new InMemoryMessages();
        $files    = new InMemoryFiles();
        $files->storeAt('billing/sent/invoice/inv-1.pdf', '%PDF');
        $files->storeAt('notification/attachments/k/report.pdf', '%PDF');

        $old = $this->emailWithFiles('01960000-0000-7000-8000-000000000e01', 'old', '2026-10-16 08:00:00');
        $old->markSent('b-1', new \DateTimeImmutable('2026-10-16 08:01:00'));
        $messages->save($old);
        $recent = $this->emailWithFiles('01960000-0000-7000-8000-000000000e02', 'recent', '2026-10-25 08:00:00');
        $messages->save($recent);
        $failed = Messages::sms(id: '01960000-0000-7000-8000-000000000503', key: 'failed', at: new \DateTimeImmutable('2026-09-01 08:00:00'));
        $failed->markFailed('refused', new \DateTimeImmutable('2026-09-01 08:00:01'));
        $messages->save($failed);

        $clock   = new FrozenClock(new \DateTimeImmutable(self::NOW));
        $jobs    = new CapturingMessageBus();
        $handler = new PurgeMessageContentsHandler($messages, $jobs, $clock, new NullLogger());
        self::assertSame(2, $handler(new PurgeMessageContents()));
        [$batch] = $jobs->dispatched[0];
        self::assertInstanceOf(PurgeMessageContentBatch::class, $batch);
        self::assertSame(['01960000-0000-7000-8000-000000000e01', '01960000-0000-7000-8000-000000000503'], $batch->messageIds);

        $batchHandler = new PurgeMessageContentBatchHandler($messages, $jobs, $clock);
        self::assertSame(2, $batchHandler($batch));
        self::assertSame(0, $batchHandler($batch), 'nothing twice');
        self::assertSame(0, $batchHandler(new PurgeMessageContentBatch(['01960000-0000-7000-8000-00000000dead'])), 'gone meanwhile');
        self::assertSame([], $files->deleted, 'no file deleted before the purge is committed');

        [$delete] = $jobs->dispatched[1];
        self::assertInstanceOf(DeleteMessageFiles::class, $delete);
        self::assertCount(2, $jobs->dispatched, 'no file job for a batch without files');
        new DeleteMessageFilesHandler($files)($delete);

        $purged = $messages->get(OutboundMessageId::fromString('01960000-0000-7000-8000-000000000e01'));
        self::assertNull($purged->recipient());
        self::assertNull($purged->content());
        self::assertSame([DeliveryStatus::SENT, 'invoice', 'Envoi de facture'], [$purged->status(), $purged->subject()->type, $purged->subjectLine()]);
        self::assertSame(['notification/attachments/k/report.pdf'], $files->deleted);
        self::assertArrayHasKey('billing/sent/invoice/inv-1.pdf', $files->files, "Billing's PDF is never touched");

        $sms = $messages->get(OutboundMessageId::fromString('01960000-0000-7000-8000-000000000503'));
        self::assertSame(['', 1], [$sms->content()?->smsText, $sms->segments()], 'never sent: counted from its creation; segments kept');
        self::assertNotNull($messages->get(OutboundMessageId::fromString('01960000-0000-7000-8000-000000000e02'))->recipient());
    }

    /**
     * N7: the purge is split in batches of 200, each its own job.
     */
    public function testThePurgeIsQueuedInBatches(): void
    {
        $messages = new InMemoryMessages();
        for ($i = 1; $i <= 450; ++$i) {
            $messages->save(Messages::sms(id: \sprintf('01960000-0000-7000-8000-%012d', $i), key: 'k-' . $i, at: new \DateTimeImmutable('2026-01-01 08:00:00')));
        }
        $jobs = new CapturingMessageBus();

        self::assertSame(450, new PurgeMessageContentsHandler($messages, $jobs, new FrozenClock(new \DateTimeImmutable(self::NOW)), new NullLogger())(new PurgeMessageContents()));

        self::assertSame([200, 200, 50], array_map(static fn (array $job): int => $job[0] instanceof PurgeMessageContentBatch ? \count($job[0]->messageIds) : 0, $jobs->dispatched));
    }

    public function testEarlyWebhooksAreRematched(): void
    {
        $messages  = new InMemoryMessages();
        $unmatched = new InMemoryUnmatchedEvents($messages);
        $buses     = new CapturingBuses();
        $sms       = Messages::sms();
        $sms->markSent('brevo-1', new \DateTimeImmutable('2026-10-21 09:31:00'));
        $messages->save($sms);
        $unmatched->keep(self::kept('u-1', 'brevo-1', '2026-10-21T09:31:01+00:00'));
        $unmatched->keep(self::kept('u-2', 'brevo-2', '2026-10-21T09:31:01+00:00'));

        $unmatched->keep(self::kept('u-3', 'brevo-1', '2026-10-21T09:31:02+00:00'));
        $jobs = new CapturingMessageBus();

        self::assertSame(1, new RematchDeliveryEventsHandler($unmatched, $jobs)(new RematchDeliveryEvents()), 'one job per provider id');
        [$replay] = $jobs->dispatched[0];
        self::assertInstanceOf(ReplayDeliveryEvents::class, $replay);
        self::assertSame(['SMS', 'brevo-1'], [$replay->channel, $replay->externalId]);

        $replayer = new ReplayDeliveryEventsHandler($unmatched, new DeliveryEventReplayer($messages, $unmatched, $buses->publisher()));
        self::assertSame(2, $replayer($replay));
        self::assertSame(0, $replayer($replay), 'a job queued twice finds nothing left');
        self::assertSame(DeliveryStatus::DELIVERED, $messages->get($sms->id())->status());
        self::assertSame(['u-2'], array_keys($unmatched->events));
    }

    /**
     * AC 11: events kept more than 24 h are matched one last time, then dropped and logged.
     */
    public function testStaleUnmatchedEventsAreDroppedAndLogged(): void
    {
        $messages  = new InMemoryMessages();
        $unmatched = new InMemoryUnmatchedEvents($messages);
        $buses     = new CapturingBuses();
        $logger    = new BufferingLogger();
        $sms       = Messages::sms();
        $sms->markSent('brevo-1', new \DateTimeImmutable('2026-10-19 09:31:00'));
        $messages->save($sms);
        $unmatched->keep(self::kept('u-1', 'brevo-1', '2026-10-19T09:31:01+00:00'));
        $unmatched->keep(self::kept('u-2', 'brevo-2', '2026-10-19T09:31:01+00:00'));
        $unmatched->keep(self::kept('u-3', 'brevo-3', '2026-10-21T01:00:00+00:00'));

        $handler = new PurgeUnmatchedDeliveryEventsHandler($unmatched, new DeliveryEventReplayer($messages, $unmatched, $buses->publisher()), new FrozenClock(new \DateTimeImmutable('2026-10-21T03:53:00+02:00')), $logger);

        self::assertSame(1, $handler(new PurgeUnmatchedDeliveryEvents()));
        self::assertSame(DeliveryStatus::DELIVERED, $messages->get($sms->id())->status(), 'matched one last time');
        self::assertSame(['u-3'], array_keys($unmatched->events), 'the recent one stays');
        $logs = $logger->cleanLogs();
        self::assertIsArray($logs[0]);
        self::assertSame('Delivery event dropped: no message ever carried its provider id.', $logs[0][1]);
    }

    private static function kept(string $id, string $externalId, string $receivedAt): UnmatchedDeliveryEvent
    {
        return new UnmatchedDeliveryEvent($id, Channel::SMS, $externalId, DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable($receivedAt)), new \DateTimeImmutable($receivedAt));
    }

    private function emailWithFiles(string $id, string $key, string $at): OutboundMessage
    {
        return OutboundMessage::queue(
            OutboundMessageId::fromString($id),
            Messages::CLINIC,
            Messages::CLIENT,
            MessagePurpose::INVOICE_SENT,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 't.lefebvre@orange.fr', 'Thomas Lefebvre'),
            SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.app', null, 'Montmartre'),
            SubjectReference::of('invoice', 'inv-1'),
            IdempotencyKey::fromString($key),
            RenderedContent::email('Votre facture', '<p>x</p>', 'x'),
            [
                Attachment::of('billing/sent/invoice/inv-1.pdf', 'FA.pdf', 'application/pdf', AttachmentOwner::REQUESTER),
                Attachment::of('notification/attachments/k/report.pdf', 'CR.pdf', 'application/pdf', AttachmentOwner::NOTIFICATION),
            ],
            new \DateTimeImmutable($at),
        );
    }
}
