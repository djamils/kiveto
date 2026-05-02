<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\Command\OpenMicrochipRegistryLookup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class OpenMicrochipRegistryLookup implements CommandInterface
{
    public function __construct(
        public string $chipNumber,
        public string $clinicId,
    ) {
    }
}
