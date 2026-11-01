<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The contact details of an active owner account (IdentityAccess). */
final readonly class OwnerAccount
{
    /**
     * @param string $phone E.164
     */
    public function __construct(
        public string $userId,
        public string $phone,
        public ?string $email,
        public bool $emailVerified,
    ) {
    }

    /** The address messages may go to: a verified one only. */
    public function verifiedEmail(): ?string
    {
        return $this->emailVerified ? $this->email : null;
    }
}
