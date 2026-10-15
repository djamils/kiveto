<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\FindByMicrochip;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's animal carrying a microchip, as read at the desk; answers an
 * AnimalMicrochipView or null.
 */
final readonly class FindByMicrochip implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $microchipNumber,
    ) {
    }
}
