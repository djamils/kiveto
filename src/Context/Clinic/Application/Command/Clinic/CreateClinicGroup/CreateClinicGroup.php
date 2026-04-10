<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\CreateClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateClinicGroup implements CommandInterface
{
    public function __construct(
        public string $name,
    ) {
    }
}
