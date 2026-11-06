<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Clinic;

/**
 * The person cannot be invited (incomplete form, disabled account, already
 * a member…); the message is shown as is.
 */
final class InvitationRefusedException extends \RuntimeException
{
}
