<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeMessageContents;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\PurgeMessageContentBatch\PurgeMessageContentBatch;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A message counts from when it was handed over, or from its creation when
 * it never was (failed, held back by the cap). Files whose owner is the
 * requesting context (an invoice PDF kept by Billing) are never touched.
 *
 * Only selects: the messages are purged by batches of BATCH, each its own
 * `async` job and transaction (PurgeMessageContentBatch). A batch that keeps
 * failing (e.g. a message edited concurrently) leaves its messages for the
 * next night; MAX_PER_RUN bounds a night's work, the rest follows.
 */
#[AsMessageHandler]
final readonly class PurgeMessageContentsHandler
{
    public const int RETENTION_MONTHS = 24;
    public const int BATCH            = 200;
    public const int MAX_PER_RUN      = 20000;

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int how many messages were queued for the purge
     */
    public function __invoke(PurgeMessageContents $command): int
    {
        $before = $this->clock->now()->modify(\sprintf('-%d months', self::RETENTION_MONTHS));
        $ids    = $this->messages->findContentToPurge($before, self::MAX_PER_RUN);

        foreach (array_chunk($ids, self::BATCH) as $batch) {
            $this->commandBus->dispatch(new PurgeMessageContentBatch(array_map(static fn (OutboundMessageId $id): string => $id->toString(), $batch)));
        }

        $this->logger->info('Message contents queued for purge.', ['count' => \count($ids), 'sentBefore' => $before->format(\DateTimeInterface::ATOM)]);

        return \count($ids);
    }
}
