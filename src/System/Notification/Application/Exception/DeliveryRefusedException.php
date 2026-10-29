<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Exception;

/**
 * The provider refused the message for good (an HTTP 4xx other than 408 and
 * 429, an attachment that cannot be read, delivery disabled): retrying would
 * not help, the message is recorded FAILED with the reason.
 */
final class DeliveryRefusedException extends \RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }
}
