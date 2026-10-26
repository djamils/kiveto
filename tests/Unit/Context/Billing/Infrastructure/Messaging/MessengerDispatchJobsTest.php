<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Messaging;

use App\Context\Billing\Application\Command\SendInvoiceEmail\SendInvoiceEmail;
use App\Context\Billing\Infrastructure\Messaging\MessengerDispatchJobs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDispatchJobsTest extends TestCase
{
    public function testTheSendIsQueuedOnTheRawCommandBus(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sent[] = Envelope::wrap($message, $stamps);
            }
        };

        new MessengerDispatchJobs($bus)->sendInvoiceEmail('d-1');

        $message = $bus->sent[0]->getMessage();
        self::assertInstanceOf(SendInvoiceEmail::class, $message);
        self::assertSame('d-1', $message->dispatchId);
    }
}
