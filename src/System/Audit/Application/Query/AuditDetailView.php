<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query;

final readonly class AuditDetailView
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
