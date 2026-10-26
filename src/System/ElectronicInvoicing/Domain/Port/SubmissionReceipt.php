<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

final readonly class SubmissionReceipt
{
    public function __construct(
        public string $flowId,
        public \DateTimeImmutable $submittedAt,
    ) {
    }
}
