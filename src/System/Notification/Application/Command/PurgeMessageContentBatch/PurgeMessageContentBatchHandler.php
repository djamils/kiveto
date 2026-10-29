<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeMessageContentBatch;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Erases the bodies and recipient of a batch of messages. Their
 * Notification-owned files are deleted by DeleteMessageFiles, queued in this
 * transaction: it only runs once the purge is committed, so that a rolled
 * back batch never loses a file its message still references. A message
 * already purged or gone is skipped.
 */
#[AsMessageHandler]
final readonly class PurgeMessageContentBatchHandler
{
    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many messages were purged
     */
    public function __invoke(PurgeMessageContentBatch $command): int
    {
        $now    = $this->clock->now();
        $files  = [];
        $purged = 0;

        foreach ($command->messageIds as $id) {
            try {
                $message = $this->messages->get(OutboundMessageId::fromString($id));
            } catch (OutboundMessageNotFoundException) {
                continue;
            }

            if (!$message->purgeContent($now)) {
                continue;
            }

            foreach ($message->attachments() as $attachment) {
                if (AttachmentOwner::NOTIFICATION === $attachment->owner) {
                    $files[] = $attachment->storageKey;
                }
            }

            $this->messages->save($message);
            ++$purged;
        }

        if ([] !== $files) {
            $this->commandBus->dispatch(new DeleteMessageFiles($files));
        }

        return $purged;
    }
}
