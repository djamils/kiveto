<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Domain\ValueObject\UserType;
use App\Shared\Application\Bus\CommandInterface;

final readonly class RegisterUser implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $plainPassword,
        public UserType $type,
    ) {
    }
}
