<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MaskPortalRecipient;

use App\Shared\Application\Bus\CommandInterface;

/**
 * An owner account was deleted: the messages Kiveto sent to it (one-time
 * codes and account notices, MessagePurpose::isPortalAccountMessage()) at
 * its phone or e-mail lose their recipient and bodies, as the 24-month
 * retention would do; those still queued are cancelled, never sent (see
 * MessagePurpose::isCancelledAtAccountDeletion()). The clinics' messages are
 * kept. Idempotent.
 */
final readonly class MaskPortalRecipient implements CommandInterface
{
    public function __construct(
        public string $phone,
        public ?string $email = null,
    ) {
    }
}
