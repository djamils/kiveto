<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * A requested availability range out of bounds (S4c): malformed dates, `from`
 * after `to`, more than 14 days, or `from` before the clinic's local today.
 * The message is French and shown as is; `field` names the offending
 * parameter. The API answers 422 `validation_failed`.
 */
final class InvalidAvailabilityRangeException extends \DomainException
{
    private function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function malformed(string $field): self
    {
        return new self($field, 'La date doit être au format AAAA-MM-JJ.');
    }

    public static function reversed(): self
    {
        return new self('to', 'La date de fin doit être postérieure ou égale à la date de début.');
    }

    public static function tooLong(int $maxDays): self
    {
        return new self('to', \sprintf('La période demandée ne peut pas dépasser %d jours.', $maxDays));
    }

    public static function inThePast(): self
    {
        return new self('from', 'La date de début ne peut pas être dans le passé.');
    }
}
