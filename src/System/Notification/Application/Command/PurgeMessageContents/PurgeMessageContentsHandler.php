<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeMessageContents;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A message counts from when it was handed over, or from its creation when
 * it never was (failed, held back by the cap). Files whose owner is the
 * requesting context (an invoice PDF kept by Billing) are never touched.
 */
#[AsMessageHandler]
final readonly class PurgeMessageContentsHandler
{
    public const int RETENTION_MONTHS = 24;

    private const int BATCH = 500;

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private FileStorageInterface $storage,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeMessageContents $command): int
    {
        $now    = $this->clock->now();
        $before = $now->modify(\sprintf('-%d months', self::RETENTION_MONTHS));
        $purged = 0;

        do {
            $ids = $this->messages->findContentToPurge($before, self::BATCH);

            foreach ($ids as $id) {
                $message = $this->messages->get($id);

                foreach ($message->attachments() as $attachment) {
                    if (AttachmentOwner::NOTIFICATION === $attachment->owner) {
                        $this->storage->delete($attachment->storageKey);
                    }
                }

                $message->purgeContent($now);
                $this->messages->save($message);
                ++$purged;
            }
        } while (self::BATCH === \count($ids));

        $this->logger->info('Message contents purged.', ['count' => $purged, 'sentBefore' => $before->format(\DateTimeInterface::ATOM)]);

        return $purged;
    }
}
