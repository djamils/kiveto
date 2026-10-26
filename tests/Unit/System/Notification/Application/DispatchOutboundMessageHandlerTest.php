<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessageHandler;
use PHPUnit\Framework\TestCase;

final class DispatchOutboundMessageHandlerTest extends TestCase
{
    public function testTheDispatchFailsVisiblyUntilTheTransportsAreWired(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Message m-1 cannot be dispatched yet');

        new DispatchOutboundMessageHandler()(new DispatchOutboundMessage('m-1'));
    }
}
