<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Messaging;

use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Infrastructure\Messaging\DispatchFailureListener;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class DispatchFailureListenerTest extends TestCase
{
    /**
     * AC 4: retries exhausted → FAILED « Échec après plusieurs tentatives ».
     */
    public function testAnExhaustedDispatchIsRecordedFailed(): void
    {
        $buses    = new CapturingBuses();
        $listener = new DispatchFailureListener($buses, new FrozenClock(new \DateTimeImmutable('2026-10-21T10:00:00+00:00')));

        $retried = new WorkerMessageFailedEvent(new Envelope(new DispatchOutboundMessage('m-1')), 'async_external_notification', new \RuntimeException('503'));
        $retried->setForRetry();
        $listener($retried);
        $listener(new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async', new \RuntimeException()));
        self::assertSame([], $buses->commands);

        $listener(new WorkerMessageFailedEvent(new Envelope(new DispatchOutboundMessage('m-1')), 'async_external_notification', new \RuntimeException('503')));

        $outcome = $buses->commandsOf(RecordDispatchOutcome::class);
        self::assertCount(1, $outcome);
        self::assertSame(['m-1', null, 'Échec après plusieurs tentatives', '2026-10-21T10:00:00+00:00'], [$outcome[0]->messageId, $outcome[0]->externalId, $outcome[0]->error, $outcome[0]->occurredAt]);
    }
}
