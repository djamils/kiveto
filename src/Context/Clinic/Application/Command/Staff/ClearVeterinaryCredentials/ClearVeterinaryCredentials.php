<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ClearVeterinaryCredentials;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ClearVeterinaryCredentials implements CommandInterface
{
    public function __construct(
        public string $profileId,
    ) {
    }
}
