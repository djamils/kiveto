<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Sends a one-time code to a mobile phone. `purpose` is `login` (any phone
 * the Portal serves) or `invitation` (the phone of the invitation
 * `invitationRef`, chosen by a clinic). The handler answers an
 * OtpChallengeIssued and never tells whether the phone has an account.
 * `requestContext` (client IP, device hint) keys the network and device
 * layers of the unknown-number SMS budget; without it only the global cap
 * applies.
 */
final readonly class RequestPortalOtp implements CommandInterface
{
    public function __construct(
        public string $phone,
        public string $purpose,
        public ?string $invitationRef = null,
        public ?OtpRequestContext $requestContext = null,
    ) {
    }
}
