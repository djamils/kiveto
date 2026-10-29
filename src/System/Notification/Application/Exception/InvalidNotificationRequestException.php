<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Exception;

/**
 * A notification request the templates cannot honour: a channel the purpose
 * has no template for, a missing or unexpected parameter, a parameter that
 * is neither a scalar nor a list of scalars. A requester bug, never retried.
 */
final class InvalidNotificationRequestException extends \InvalidArgumentException
{
}
