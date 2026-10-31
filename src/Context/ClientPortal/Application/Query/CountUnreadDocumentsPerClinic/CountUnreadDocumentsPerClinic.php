<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic;

use App\Shared\Application\Bus\QueryInterface;

/** The unread documents badge of each clinic of an account. Answers array<clinicId, int>. */
final readonly class CountUnreadDocumentsPerClinic implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
