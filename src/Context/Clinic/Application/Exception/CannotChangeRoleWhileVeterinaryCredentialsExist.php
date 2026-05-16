<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Exception;

final class CannotChangeRoleWhileVeterinaryCredentialsExist extends \RuntimeException
{
    public function __construct(string $membershipId, string $newRole)
    {
        parent::__construct(\sprintf(
            'Cannot change role of membership "%s" to "%s" while veterinary credentials exist.'
                . ' Clear credentials first.',
            $membershipId,
            $newRole,
        ));
    }
}
