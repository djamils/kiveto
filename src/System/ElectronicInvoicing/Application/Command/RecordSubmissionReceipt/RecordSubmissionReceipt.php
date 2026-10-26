<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordSubmissionReceipt;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordSubmissionReceipt implements CommandInterface
{
    public function __construct(
        public string $transmissionId,
        public string $flowId,
        public string $submittedAt,
    ) {
    }
}
