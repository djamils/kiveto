<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Memberships with their profile, of one clinic, of one account, or all.
 */
final readonly class ListConsoleMembers implements QueryInterface
{
    public function __construct(
        public ?string $clinicId = null,
        public ?string $userId = null,
    ) {
    }
}
