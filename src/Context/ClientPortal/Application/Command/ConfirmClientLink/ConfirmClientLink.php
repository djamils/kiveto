<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ConfirmClientLink;

use App\Shared\Application\Bus\CommandInterface;

/**
 * VetApp « Rattachement » → « Confirmer »: the account's pending links to
 * these files of the clinic become confirmed (the existing rows, never a
 * duplicate). Only the files staff were shown are listed, so a file that
 * joined the request meanwhile stays pending. Returns how many links were
 * confirmed (0: nothing was pending any more).
 */
final readonly class ConfirmClientLink implements CommandInterface
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
