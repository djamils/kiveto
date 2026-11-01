<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RejectClientLink;

use App\Shared\Application\Bus\CommandInterface;

/**
 * VetApp « Rattachement » → « Refuser »: the account's pending links to
 * these files of the clinic are revoked (`link_rejected`), so the clinic is
 * never suggested again for them. No consent changes (D5). Returns how many
 * links were rejected (0: nothing was pending any more).
 */
final readonly class RejectClientLink implements CommandInterface
{
    /**
     * @param list<string> $clientIds
     */
    public function __construct(
        public string $clinicId,
        public string $userId,
        public array $clientIds,
    ) {
    }
}
