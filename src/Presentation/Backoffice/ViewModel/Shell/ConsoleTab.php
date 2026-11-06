<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Shell;

final readonly class ConsoleTab
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $active = false,
        public ?int $count = null,
        public bool $preAlpha = false,
    ) {
    }
}
