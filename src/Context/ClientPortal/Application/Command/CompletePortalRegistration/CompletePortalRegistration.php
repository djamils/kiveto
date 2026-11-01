<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\CompletePortalRegistration;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Organic arrival: an owner without invitation creates their account with
 * the registration grant of their sign-in, their names and the current
 * terms. Answers a RegistrationOutcome; TermsOutdatedException before any
 * write; IdentityAccess's PortalAccountExistsException when an active
 * account already holds the phone (nothing written).
 */
final readonly class CompletePortalRegistration implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $registrationToken,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public string $termsVersion,
    ) {
    }
}
