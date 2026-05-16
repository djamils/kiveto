<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ApplySnapshotDiff;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ApplySnapshotDiff implements CommandInterface
{
    public function __construct(
        public string $snapshotId,
    ) {
    }
}
