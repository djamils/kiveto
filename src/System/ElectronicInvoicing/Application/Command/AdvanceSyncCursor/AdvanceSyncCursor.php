<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\AdvanceSyncCursor;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AdvanceSyncCursor implements CommandInterface
{
    public function __construct(
        public string $organisationId,
        public string $updatedAfter,
        public string $lastFlowId,
    ) {
    }
}
