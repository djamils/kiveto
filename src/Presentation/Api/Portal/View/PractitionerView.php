<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\Scheduling\Application\Query\ListBookablePractitioners\BookablePractitioner;

/** A practitioner as the owner sees it: `displayName` null without staff profile. */
final readonly class PractitionerView
{
    public function __construct(
        public string $id,
        public ?string $displayName,
    ) {
    }

    public static function of(BookablePractitioner $practitioner): self
    {
        return new self($practitioner->id, $practitioner->displayName);
    }
}
