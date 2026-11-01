<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterPortalUser;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Creates the owner account of the phone a registration grant proved
 * (`registrationToken`, in clear, from the `registration_required` answer
 * of a sign-in). Run in the caller's transaction. Answers a
 * PortalUserRegistration; PortalAccountExistsException when an active owner
 * account already holds the phone.
 */
final readonly class RegisterPortalUser implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $registrationToken,
        public ?string $email = null,
    ) {
    }
}
