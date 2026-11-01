<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * What staff chose to confirm a booking request with is not allowed: a
 * client file that is no match of the account's phone in the clinic, or an
 * animal that is not one of that client's. The message is shown as is.
 */
final class InvalidBookingChoiceException extends \DomainException
{
    public const string CLIENT = 'client';
    public const string ANIMAL = 'animal';

    /**
     * @param string $field client|animal
     */
    public function __construct(public readonly string $field)
    {
        parent::__construct(self::CLIENT === $field
            ? 'Choisissez un dossier client proposé, ou créez-en un.'
            : 'Choisissez un animal de ce client, ou créez-le.');
    }
}
