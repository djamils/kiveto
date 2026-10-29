<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** The owner account: identity from IdentityAccess, name and address from the owner profile. */
final readonly class AccountView
{
    public function __construct(
        public string $id,
        public ?string $firstName,
        public ?string $lastName,
        /** Unverified, possibly null. */
        public ?string $email,
        public string $maskedPhone,
        public ?AddressView $address,
    ) {
    }
}
