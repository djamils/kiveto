<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * A motif or online booking setting out of its bounds. The message is French
 * and shown as is; `field` names the offending form field.
 */
final class InvalidBookingConfigurationException extends \DomainException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
