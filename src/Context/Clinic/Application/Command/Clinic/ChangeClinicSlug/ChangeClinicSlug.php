<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ChangeClinicSlug;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicSlug implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $slug,
    ) {
    }
}
