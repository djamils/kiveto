<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListBookableMotifs;

final readonly class BookableMotif
{
    /**
     * @param ?string $suggestionReason a French label (« Rappel de vaccin en retard »), when suggested
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $description,
        public int $durationMin,
        public bool $suggested,
        public ?string $suggestionReason,
    ) {
    }
}
