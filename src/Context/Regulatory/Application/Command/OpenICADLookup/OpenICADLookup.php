<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\Command\OpenICADLookup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class OpenICADLookup implements CommandInterface
{
    public function __construct(
        public string $chipNumber,
        public string $clinicId,
    ) {
    }
}
