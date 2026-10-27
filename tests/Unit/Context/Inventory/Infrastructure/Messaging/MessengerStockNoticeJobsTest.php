<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Messaging;

use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStock;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Infrastructure\Messaging\MessengerStockNoticeJobs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class MessengerStockNoticeJobsTest extends TestCase
{
    /** @var list<Envelope> */
    private array $sent = [];

    public function testTheNoticeIsQueued(): void
    {
        new MessengerStockNoticeJobs($this->bus())->notifyCriticalStock('clinic-1', 'alert-1');

        $message = $this->sent[0]->getMessage();
        self::assertInstanceOf(NotifyCriticalStock::class, $message);
        self::assertSame(['clinic-1', 'alert-1'], [$message->clinicId, $message->alertId]);
    }

    public function testEachClinicsExpiryScanIsSentToAsyncByStamp(): void
    {
        new MessengerStockNoticeJobs($this->bus())->notifyExpiringLots('clinic-1');

        $message = $this->sent[0]->getMessage();
        self::assertInstanceOf(NotifyExpiringLots::class, $message);
        self::assertSame('clinic-1', $message->clinicId);
        self::assertSame(['async'], $this->sent[0]->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function record(Envelope $envelope): Envelope
    {
        return $this->sent[] = $envelope;
    }

    private function bus(): MessageBusInterface
    {
        return new readonly class($this) implements MessageBusInterface {
            public function __construct(private MessengerStockNoticeJobsTest $test)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->test->record(Envelope::wrap($message, $stamps));
            }
        };
    }
}
