<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\UpdateListingSettings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * VetApp « Paramètres › Annuaire », saved whole. The projection is refreshed
 * in the same transaction, so the directory follows at once.
 */
final readonly class UpdateListingSettings implements CommandInterface
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $clinicId,
        public bool $listed,
        public bool $acceptsNewClients,
        public array $tags,
    ) {
    }
}
