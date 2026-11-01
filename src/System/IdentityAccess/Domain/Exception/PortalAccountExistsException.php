<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/**
 * A registration found an active owner account on the proven phone (created
 * meanwhile, e.g. by an invitation acceptance): nothing is created and the
 * registration grant stays unused.
 */
final class PortalAccountExistsException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Un compte Portal existe déjà pour ce numéro : connectez-vous.');
    }
}
