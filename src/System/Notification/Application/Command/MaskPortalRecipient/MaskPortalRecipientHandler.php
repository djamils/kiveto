<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MaskPortalRecipient;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Same erasure as the retention purge (OutboundMessage::purgeContent()):
 * the metadata stays, Notification-owned files are deleted once the caller's
 * transaction commits (DeleteMessageFiles, `async`).
 *
 * Each message is re-read under its row lock (OutboundMessageRepositoryInterface::getForUpdate()),
 * in the caller's transaction: a dispatch worker that claimed or sent it
 * meanwhile is seen, and never makes the erasure fail on a stale version.
 * What happens to each is OutboundMessage::eraseForDeletedAccount(): a
 * queued one not yet claimed by its dispatch is cancelled when moot, one
 * already claimed is only masked (it may be with the provider), the queued
 * e-mail change notice is masked once its hand-over outcome is recorded.
 */
#[AsMessageHandler]
final readonly class MaskPortalRecipientHandler
{
    public const string CANCELLED_REASON = 'Compte Portal supprimé avant l\'envoi';

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @return int how many messages were masked, cancelled or flagged for masking
     */
    public function __invoke(MaskPortalRecipient $command): int
    {
        $addresses = array_values(array_filter([$command->phone, $command->email], static fn (?string $address): bool => null !== $address));
        $now       = $this->clock->now();
        $files     = [];
        $masked    = 0;

        foreach ($this->messages->findPortalAccountMessages($command->userId, $addresses) as $id) {
            $message = $this->messages->getForUpdate($id);

            if (!$message->eraseForDeletedAccount(self::CANCELLED_REASON, $now)) {
                continue;
            }

            if (null !== $message->contentPurgedAt()) {
                foreach ($message->attachments() as $attachment) {
                    if (AttachmentOwner::NOTIFICATION === $attachment->owner) {
                        $files[] = $attachment->storageKey;
                    }
                }
            }

            $this->messages->save($message);
            $this->domainEventPublisher->publish($message);
            ++$masked;
        }

        if ([] !== $files) {
            $this->commandBus->dispatch(new DeleteMessageFiles($files));
        }

        return $masked;
    }
}
