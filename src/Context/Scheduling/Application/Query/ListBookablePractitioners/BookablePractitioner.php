<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookablePractitioners;

final readonly class BookablePractitioner
{
    /**
     * @param ?string $displayName the printed staff name (« Dr Claire Rousseau »), null without staff profile
     */
    public function __construct(
        public string $id,
        public ?string $displayName,
    ) {
    }
}
