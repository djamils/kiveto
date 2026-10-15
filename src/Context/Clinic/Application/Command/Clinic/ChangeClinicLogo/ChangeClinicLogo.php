<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ChangeClinicLogo;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Points the clinic at the stored file of its logo; null takes the logo off its
 * documents.
 */
final readonly class ChangeClinicLogo implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $logoKey,
    ) {
    }
}
