<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal;

final readonly class MedicalAlertView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public ?string $note,
    ) {
    }
}
