<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The agenda's « Confirmer » of a pending online request made by a new
 * client (portal source, no owner yet): staff resolve it from « Demandes
 * Portal », where the client file and the animal are chosen first (or the
 * agenda's edit tried to set them, `ownerChange()`). The message is shown
 * to the user as is.
 */
final class NewClientRequestException extends \DomainException
{
    public function __construct(string $message = 'À traiter dans Demandes Portal.')
    {
        parent::__construct($message);
    }

    /** The agenda's edit of a new client's request tried to set its client or its animal. */
    public static function ownerChange(): self
    {
        return new self('Demande d’un nouveau client : le client et l’animal se choisissent dans « Demandes Portal ».');
    }
}
