<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Scheduler;

use App\Shared\Infrastructure\Scheduler\SchedulerHeartbeat;
use App\Shared\Infrastructure\Scheduler\SchedulerHeartbeatHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SchedulerHeartbeatHandlerTest extends TestCase
{
    public function testHeartbeatIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('scheduler heartbeat');

        new SchedulerHeartbeatHandler($logger)(new SchedulerHeartbeat());
    }
}
