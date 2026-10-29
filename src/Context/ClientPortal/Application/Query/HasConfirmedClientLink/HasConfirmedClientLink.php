<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\HasConfirmedClientLink;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether an owner account reaches a clinic through a confirmed link (the
 * Portal API's tenant guard: otherwise 404). Answers a bool.
 */
final readonly class HasConfirmedClientLink implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
