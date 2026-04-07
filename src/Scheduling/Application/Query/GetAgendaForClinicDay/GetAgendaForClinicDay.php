<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetAgendaForClinicDay;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetAgendaForClinicDay implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $date,
        public ?string $practitionerUserId = null,
    ) {
    }
}
