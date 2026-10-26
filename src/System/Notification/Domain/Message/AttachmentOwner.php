<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Who a stored attachment file belongs to: the requesting context (never
 * touched by Notification) or Notification itself (produced for the send
 * only, deleted when the message content is purged).
 */
enum AttachmentOwner: string
{
    case REQUESTER    = 'REQUESTER';
    case NOTIFICATION = 'NOTIFICATION';
}
