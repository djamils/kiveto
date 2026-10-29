<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Invitation;

/**
 * How the invitation link reaches the client. One-time codes always go to
 * the invitation's mobile, whatever this channel.
 */
enum InvitationChannel: string
{
    case SMS   = 'sms';
    case EMAIL = 'email';
}
