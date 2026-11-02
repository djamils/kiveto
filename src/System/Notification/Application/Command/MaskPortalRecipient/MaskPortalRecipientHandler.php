<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MaskPortalRecipient;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Same erasure as the retention purge (OutboundMessage::purgeContent()):
 * the metadata stays, Notification-owned files are deleted once the caller's
 * transaction commits (DeleteMessageFiles, `async`).
 *
 * A message still QUEUED (not handed over to the provider) is cancelled and
 * masked when its purpose is moot once the account is gone
 * (MessagePurpose::isCancelledAtAccountDeletion()): the dispatch still on
 * the transport finds it CANCELLED and sends nothing. A queued critical one
 * (the e-mail change notice) keeps its recipient for its dispatch.
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
     * @return int how many messages were masked (cancelled ones included)
     */
    public function __invoke(MaskPortalRecipient $command): int
    {
        $addresses = null === $command->email ? [$command->phone] : [$command->phone, $command->email];
        $now       = $this->clock->now();
        $files     = [];
        $masked    = 0;

        foreach ($this->messages->findPortalAccountMessagesTo($addresses) as $id) {
            $message = $this->messages->get($id);
            $queued  = DeliveryStatus::QUEUED === $message->status();

            if ($queued && !$message->purpose()->isCancelledAtAccountDeletion()) {
                continue;
            }

            // Masked first: the cancellation's status event carries no recipient.
            $message->purgeContent($now);
            if ($queued) {
                $message->cancel(self::CANCELLED_REASON, $now);
            }

            foreach ($message->attachments() as $attachment) {
                if (AttachmentOwner::NOTIFICATION === $attachment->owner) {
                    $files[] = $attachment->storageKey;
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
