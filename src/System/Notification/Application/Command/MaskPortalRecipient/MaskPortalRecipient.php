<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MaskPortalRecipient;

use App\Shared\Application\Bus\CommandInterface;

/**
 * An owner account was deleted: the messages Kiveto sent to it (one-time
 * codes and account notices, MessagePurpose::isPortalAccountMessage()) —
 * those of that account (userId, whatever address they went to, earlier
 * e-mail addresses included) and those to its phone or e-mail — lose their
 * recipient and bodies, as the 24-month retention would do; those still
 * queued are cancelled, never sent (see MessagePurpose::isCancelledAtAccountDeletion()).
 * The clinics' messages are kept. Idempotent.
 */
final readonly class MaskPortalRecipient implements CommandInterface
{
    public function __construct(
        public string $userId,
        public ?string $phone = null,
        public ?string $email = null,
    ) {
    }
}
