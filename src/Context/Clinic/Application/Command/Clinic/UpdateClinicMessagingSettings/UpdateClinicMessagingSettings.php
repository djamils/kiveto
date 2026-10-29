<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The SMS sender name and the SMS overage cap, replaced together.
 */
final readonly class UpdateClinicMessagingSettings implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $smsSender,
        public int $overageCapSegments,
    ) {
    }
}
