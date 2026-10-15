<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RecordUserLogin;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordUserLogin implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
