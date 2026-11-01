<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The agenda's « Confirmer » of a pending online request made by a new
 * client (portal source, no owner yet): staff resolve it from « Demandes
 * Portal », where the client file and the animal are chosen first. The
 * message is shown to the user as is.
 */
final class NewClientRequestException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('À traiter dans Demandes Portal.');
    }
}
