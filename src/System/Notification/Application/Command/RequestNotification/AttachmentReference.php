<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RequestNotification;

/**
 * A file to attach to an e-mail, already stored by the requester under a
 * deterministic key (binaries never travel in messages).
 */
final readonly class AttachmentReference
{
    public const string OWNER_REQUESTER    = 'REQUESTER';
    public const string OWNER_NOTIFICATION = 'NOTIFICATION';

    /**
     * @param string $owner OWNER_REQUESTER (the requester keeps the file, e.g. Billing's invoice PDF)
     *                      or OWNER_NOTIFICATION (produced for this send only, deleted with the message content)
     */
    public function __construct(
        public string $storageKey,
        public string $filename,
        public string $mime,
        public string $owner,
    ) {
    }
}
