<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ReviseActDetails;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ReviseActDetails implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
        public ?string $description,
        public string $category,
        public int $estimatedDurationMinutes,
        public bool $requiresAnesthesia,
    ) {
    }
}
