<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** No invitation answers this token (unknown, or replaced by a resend). */
final class InvitationInvalidException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Invitation not found.');
    }
}
