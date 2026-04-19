<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RenameStaffProfile;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameStaffProfile implements CommandInterface
{
    public function __construct(
        public string $profileId,
        public string $firstName,
        public string $lastName,
        public string $displayName,
    ) {
    }
}
