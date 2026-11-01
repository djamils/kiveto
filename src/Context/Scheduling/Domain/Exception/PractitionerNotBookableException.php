<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The user cannot take appointments at the clinic (no active veterinary or
 * assistant membership).
 */
final class PractitionerNotBookableException extends \DomainException
{
    public static function at(string $practitionerUserId, string $clinicId): self
    {
        return new self(\sprintf(
            'User "%s" is not eligible as practitioner for clinic "%s".',
            $practitionerUserId,
            $clinicId,
        ));
    }
}
