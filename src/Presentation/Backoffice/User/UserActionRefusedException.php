<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\User;

/**
 * An action on a clinic account the console could not do; its message is
 * shown as is, in French.
 */
final class UserActionRefusedException extends \RuntimeException
{
}
