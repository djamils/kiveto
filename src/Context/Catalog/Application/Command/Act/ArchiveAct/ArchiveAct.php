<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ArchiveAct;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ArchiveAct implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
    ) {
    }
}
