<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

final class EmailNotVerifiedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email is not verified.');
    }
}

