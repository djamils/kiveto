<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SetPortalConsent;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner sets the channels of one consent kind in a clinic, on every
 * confirmed-linked client file of that clinic that is not archived. Throws
 * UnknownConsentKindException, InvalidConsentChoiceException (a channel
 * other than `sms` / `email`, or none on a kind sent anyway),
 * ClinicNotLinkedException (no such file), OwnerProfileNotFoundException
 * and ConsentConflictException (a file changed concurrently).
 */
final readonly class SetPortalConsent implements CommandInterface
{
    /**
     * @param list<string> $channels empty withdraws an optional kind
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $kind,
        public array $channels,
    ) {
    }
}
