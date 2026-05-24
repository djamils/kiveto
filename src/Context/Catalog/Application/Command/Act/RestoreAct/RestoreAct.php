<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\RestoreAct;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RestoreAct implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
    ) {
    }
}
