<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

interface UserExistenceCheckerInterface
{
    public function exists(string $userId): bool;
}
