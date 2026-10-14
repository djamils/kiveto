<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shell;

/**
 * One clinic of the member, as the clinic switcher shows it.
 */
final readonly class ShellClinic
{
    public function __construct(
        public string $id,
        public string $name,
        public string $initial,
        public string $color,
        public string $subtitle,
        public bool $isCurrent,
        public bool $isDefault,
    ) {
    }
}
