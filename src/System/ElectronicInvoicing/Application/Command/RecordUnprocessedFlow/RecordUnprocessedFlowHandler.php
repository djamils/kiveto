<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow;

use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Application\Port\UnprocessedFlowsInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Keeps a flow that could not be processed so that the next runs retry it;
 * past the last attempt it is logged for support — never silently lost.
 */
#[AsMessageHandler]
final readonly class RecordUnprocessedFlowHandler
{
    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        private UnprocessedFlowsInterface $flows,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecordUnprocessedFlow $command): void
    {
        if (null === $command->reason) {
            $this->flows->resolve($command->organisationId, $command->flowId);

            return;
        }

        $attempts = $this->flows->record($command->organisationId, $command->flowId, $command->reason, $this->clock->now());

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->logger->error('A platform flow could not be processed and is no longer retried.', [
                'organisationId' => $command->organisationId,
                'flowId'         => $command->flowId,
                'reason'         => $command->reason,
                'attempts'       => $attempts,
            ]);
        }
    }
}
