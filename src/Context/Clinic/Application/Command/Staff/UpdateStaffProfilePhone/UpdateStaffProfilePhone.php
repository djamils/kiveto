<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateStaffProfilePhone implements CommandInterface
{
    public function __construct(
        public string $profileId,
        public ?string $phone,
    ) {
    }
}
