<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Messaging;

use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStock;
use App\Context\Inventory\Infrastructure\Messaging\MessengerStockNoticeJobs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerStockNoticeJobsTest extends TestCase
{
    public function testTheNoticeIsQueued(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sent[] = Envelope::wrap($message, $stamps);
            }
        };

        new MessengerStockNoticeJobs($bus)->notifyCriticalStock('clinic-1', 'alert-1');

        $message = $bus->sent[0]->getMessage();
        self::assertInstanceOf(NotifyCriticalStock::class, $message);
        self::assertSame(['clinic-1', 'alert-1'], [$message->clinicId, $message->alertId]);
    }
}
