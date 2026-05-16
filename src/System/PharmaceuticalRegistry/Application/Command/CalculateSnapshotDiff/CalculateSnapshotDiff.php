<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CalculateSnapshotDiff implements CommandInterface
{
    public function __construct(
        public string $snapshotId,
    ) {
    }
}
