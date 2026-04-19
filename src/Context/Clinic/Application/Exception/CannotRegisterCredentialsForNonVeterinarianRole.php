<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Exception;

final class CannotRegisterCredentialsForNonVeterinarianRole extends \RuntimeException
{
    public function __construct(string $role, string $membershipId)
    {
        parent::__construct(\sprintf(
            'Cannot register veterinary credentials for membership "%s" with role "%s". Only VETERINARY role can hold credentials.',
            $membershipId,
            $role,
        ));
    }
}
