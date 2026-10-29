<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/** A staff account (clinic, back-office) is identified by its e-mail address. */
final class EmailRequiredException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Un compte du personnel doit avoir une adresse e-mail.');
    }
}
