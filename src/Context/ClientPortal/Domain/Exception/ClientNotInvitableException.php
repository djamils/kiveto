<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The client cannot be invited to the Portal; `reason` tells the clinic
 * sheet why (one of the constants).
 */
final class ClientNotInvitableException extends \DomainException
{
    /** No client of that id in the clinic. */
    public const string UNKNOWN_CLIENT = 'unknown_client';
    public const string ARCHIVED       = 'archived';
    /** No phone able to receive an SMS on file. */
    public const string NO_MOBILE = 'no_mobile';
    /** The SMS-capable phone is outside the mobile ranges the Portal supports. */
    public const string MOBILE_NOT_SUPPORTED = 'mobile_not_supported';
    /** E-mail channel chosen for a client without e-mail. */
    public const string NO_EMAIL = 'no_email';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(\sprintf('Client cannot be invited to the Portal: %s.', $reason));
    }
}
