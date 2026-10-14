<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * One line of « Traité aujourd'hui ».
 */
final readonly class DoneItem
{
    public function __construct(
        public string $emoji,
        public string $label,
        public ?string $time,
    ) {
    }
}
