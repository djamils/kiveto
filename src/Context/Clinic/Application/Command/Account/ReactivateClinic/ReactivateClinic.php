<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\ReactivateClinic;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Réactiver la clinique »: its users get their access back.
 */
final readonly class ReactivateClinic implements CommandInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
