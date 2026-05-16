<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetImportHistory;

readonly class ImportHistoryView
{
    public function __construct(
        public string $id,
        public string $source,
        public string $status,
        public \DateTimeImmutable $downloadedAt,
        public ?\DateTimeImmutable $appliedAt,
        public ?string $errorMessage,
    ) {
    }
}
