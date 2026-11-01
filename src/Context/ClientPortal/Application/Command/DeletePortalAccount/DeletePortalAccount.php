<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeletePortalAccount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The erasure of a disabled owner account (`async`, queued by
 * RequestAccountDeletion). One transaction, under the per-phone lock row;
 * idempotent and safe to retry at any point. The clinics' `Client` records,
 * their consents and the clinics' messages are never touched (the clinic is
 * the controller of its records).
 */
final readonly class DeletePortalAccount implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
