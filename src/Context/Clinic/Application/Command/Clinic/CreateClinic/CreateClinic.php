<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\CreateClinic;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateClinic implements CommandInterface
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $timeZone,
        public string $locale,
        public ?string $clinicGroupId = null,
    ) {
    }
}
