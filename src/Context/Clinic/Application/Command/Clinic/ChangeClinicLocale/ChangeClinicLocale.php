<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ChangeClinicLocale;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicLocale implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $locale,
    ) {
    }
}
