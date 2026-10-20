<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordTransmissionBlocked;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordTransmissionBlocked implements CommandInterface
{
    public function __construct(
        public string $transmissionId,
        public string $reason,
        public ?string $detail,
    ) {
    }
}
