<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordTransmissionNotRequired;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordTransmissionNotRequired implements CommandInterface
{
    public function __construct(
        public string $transmissionId,
        public string $reason,
    ) {
    }
}
