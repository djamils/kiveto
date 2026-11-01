<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\ClientPortal\Application\Query\ListBookableMotifs\BookableMotif;

/** A motif the owner may book online, suggested for the pet's overdue or due care. */
final readonly class BookingMotifView
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $description,
        public int $durationMin,
        public bool $suggested,
        /** « Rappel de vaccin en retard », « Rappel de vaccin à prévoir »…, null when not suggested. */
        public ?string $suggestionReason,
    ) {
    }

    public static function of(BookableMotif $motif): self
    {
        return new self($motif->id, $motif->label, $motif->description, $motif->durationMin, $motif->suggested, $motif->suggestionReason);
    }
}
