<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Messaging;

use App\Context\Billing\Application\Command\SendInvoiceEmail\SendInvoiceEmail;
use App\Context\Billing\Application\Command\SendUnpaidReminder\SendUnpaidReminder;
use App\Context\Billing\Infrastructure\Messaging\MessengerDispatchJobs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDispatchJobsTest extends TestCase
{
    public function testTheJobsAreQueuedOnTheRawCommandBus(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sent[] = Envelope::wrap($message, $stamps);
            }
        };

        $jobs = new MessengerDispatchJobs($bus);
        $jobs->sendInvoiceEmail('d-1');
        $jobs->sendUnpaidReminder('d-2');

        [$send, $reminder] = $bus->sent;
        self::assertInstanceOf(SendInvoiceEmail::class, $send->getMessage());
        self::assertSame('d-1', $send->getMessage()->dispatchId);
        self::assertInstanceOf(SendUnpaidReminder::class, $reminder->getMessage());
        self::assertSame('d-2', $reminder->getMessage()->dispatchId);
    }
}
