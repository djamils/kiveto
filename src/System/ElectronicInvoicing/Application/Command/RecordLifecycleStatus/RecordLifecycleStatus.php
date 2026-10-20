<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordLifecycleStatus implements CommandInterface
{
    public function __construct(
        public string $transmissionId,
        public int $statusCode,
        public ?string $reasonCode,
        public ?string $reasonText,
        public string $sourceFlowId,
        public string $at,
    ) {
    }
}
