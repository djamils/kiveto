<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientMustHaveAtLeastOneContactMethodException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Client must have at least one contact method (phone or email).');
    }
}
