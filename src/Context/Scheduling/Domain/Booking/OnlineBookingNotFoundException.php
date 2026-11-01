<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * What a client asked to book online does not exist for them: a motif that is
 * unknown, of another clinic, archived or not online, or a practitioner not
 * bookable for that motif (not listed, or no longer eligible), or an
 * appointment unknown or of another clinic. The API answers 404 `not_found`.
 */
final class OnlineBookingNotFoundException extends \DomainException
{
    public const string MOTIF        = 'motif';
    public const string PRACTITIONER = 'practitioner';
    public const string APPOINTMENT  = 'appointment';

    private function __construct(public readonly string $subject, string $id)
    {
        parent::__construct(\sprintf('Online booking %s "%s" not found.', $subject, $id));
    }

    public static function motif(string $motifId): self
    {
        return new self(self::MOTIF, $motifId);
    }

    public static function practitioner(string $practitionerId): self
    {
        return new self(self::PRACTITIONER, $practitionerId);
    }

    public static function appointment(string $appointmentId): self
    {
        return new self(self::APPOINTMENT, $appointmentId);
    }
}
