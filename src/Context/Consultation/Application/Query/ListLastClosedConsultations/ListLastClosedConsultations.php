<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListLastClosedConsultations;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The last visit of several animals at once, as SearchConsultations
 * restricted to one animal, closed, newest first, reads it: a page naming
 * many patients reads them in three queries instead of five per patient.
 * Answers `array<string, LastClosedConsultation>` by animal id.
 */
final readonly class ListLastClosedConsultations implements QueryInterface
{
    /**
     * @param list<string> $animalIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
    ) {
    }
}
