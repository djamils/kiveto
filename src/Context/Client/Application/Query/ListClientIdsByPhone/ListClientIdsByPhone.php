<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientIdsByPhone;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clients of the clinic one of whose phones is exactly this number
 * (`+33612345678`), archived ones included: before a second record is
 * opened, the directory asks whether the number already belongs to someone.
 */
final readonly class ListClientIdsByPhone implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $phone,
    ) {
    }
}
