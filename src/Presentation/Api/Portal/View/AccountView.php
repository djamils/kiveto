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
        /** Possibly null; see emailVerified. */
        public ?string $email,
        /** The owner proved the e-mail; a verified e-mail is a second sign-in factor. */
        public bool $emailVerified,
        public string $maskedPhone,
        public ?AddressView $address,
    ) {
    }
}
