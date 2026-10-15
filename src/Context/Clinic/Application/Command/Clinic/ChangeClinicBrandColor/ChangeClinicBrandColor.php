<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ChangeClinicBrandColor;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The colour the clinic goes by in the clinic switcher; null gives it back the
 * colour of its rank.
 */
final readonly class ChangeClinicBrandColor implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $brandColor,
    ) {
    }
}
