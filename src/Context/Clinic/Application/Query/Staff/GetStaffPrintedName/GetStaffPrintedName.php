<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName;

use App\Shared\Application\Bus\QueryInterface;

/**
 * How a member of a clinic is named in what Kiveto sends on its behalf:
 * « Dr Claire Lambert » (title without a dot), « Hugo Martin »; null when
 * the user has no staff profile in that clinic.
 */
final readonly class GetStaffPrintedName implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $userId,
    ) {
    }
}
