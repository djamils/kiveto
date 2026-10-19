<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SchedulerHeartbeatHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(SchedulerHeartbeat $heartbeat): void
    {
        $this->logger->info('scheduler heartbeat');
    }
}
