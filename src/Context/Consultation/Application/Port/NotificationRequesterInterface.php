<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * Asks the Notification context for a client e-mail.
 */
interface NotificationRequesterInterface
{
    /** @return string the id of the message */
    public function request(DocumentsMessage $message): string;
}
