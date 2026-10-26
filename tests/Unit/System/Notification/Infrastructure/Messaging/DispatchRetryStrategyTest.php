<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Messaging;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Infrastructure\Messaging\DispatchRetryStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class DispatchRetryStrategyTest extends TestCase
{
    public function testRetriesAreBoundedWithAGrowingDelay(): void
    {
        $strategy = new DispatchRetryStrategy(5, 30000, 3);
        $first    = new Envelope(new DispatchOutboundMessage('m-1'));
        $third    = $first->with(new RedeliveryStamp(2));
        $last     = $first->with(new RedeliveryStamp(5));

        // Messenger adds up to 10 % jitter.
        self::assertEqualsWithDelta(30000, $strategy->getWaitingTime($first, new \RuntimeException()), 3000);
        self::assertEqualsWithDelta(270000, $strategy->getWaitingTime($third, null), 27000);
        self::assertTrue($strategy->isRetryable($third));
        self::assertFalse($strategy->isRetryable($last), 'retries exhausted');
    }

    /**
     * AC 4: a 429 with Retry-After 60 waits at least 60 s, even on the first retry.
     */
    public function testTheProviderDelayIsHonoured(): void
    {
        $strategy = new DispatchRetryStrategy(5, 30000, 3);
        $envelope = new Envelope(new DispatchOutboundMessage('m-1'));
        $failure  = new HandlerFailedException($envelope, [new \LogicException('other'), ExternalServiceUnavailable::rateLimited('Brevo', 60)]);

        self::assertSame(60000, $strategy->getWaitingTime($envelope, $failure));
        self::assertEqualsWithDelta(30000, $strategy->getWaitingTime($envelope, ExternalServiceUnavailable::rateLimited('Brevo', 10)), 3000, 'never shorter than the growing delay');
        self::assertEqualsWithDelta(30000, $strategy->getWaitingTime($envelope, ExternalServiceUnavailable::failed('Brevo', 503)), 3000);
    }
}
