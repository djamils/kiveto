<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The animal described for a with-new-pet booking is not valid (`field`
 * `name` | `species`); the message is shown as is. The API answers 422.
 */
final class InvalidNewPetException extends \DomainException
{
    public const string NAME    = 'name';
    public const string SPECIES = 'species';

    private function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function name(int $maxLength): self
    {
        return new self(self::NAME, \sprintf('Le nom de l\'animal est obligatoire (%d caractères au plus).', $maxLength));
    }

    public static function species(): self
    {
        return new self(self::SPECIES, 'Choisissez l\'espèce de l\'animal : chien, chat, NAC ou autre.');
    }
}
