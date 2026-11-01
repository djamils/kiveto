<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MaskPortalRecipient;

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
 */
#[AsMessageHandler]
final readonly class MaskPortalRecipientHandler
{
    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many messages were masked
     */
    public function __invoke(MaskPortalRecipient $command): int
    {
        $addresses = null === $command->email ? [$command->phone] : [$command->phone, $command->email];
        $now       = $this->clock->now();
        $files     = [];
        $masked    = 0;

        foreach ($this->messages->findPortalAccountMessagesTo($addresses) as $id) {
            $message = $this->messages->get($id);
            $message->purgeContent($now);

            foreach ($message->attachments() as $attachment) {
                if (AttachmentOwner::NOTIFICATION === $attachment->owner) {
                    $files[] = $attachment->storageKey;
                }
            }

            $this->messages->save($message);
            ++$masked;
        }

        if ([] !== $files) {
            $this->commandBus->dispatch(new DeleteMessageFiles($files));
        }

        return $masked;
    }
}
