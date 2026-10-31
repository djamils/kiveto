<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationShareability;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether the report of a consultation may be shared on the owner's Portal
 * (« Partager sur le Portal »), with the ids the share needs. Public: other
 * contexts reach it through the query bus. Answers null when the
 * consultation is unknown to this clinic.
 */
final readonly class GetConsultationShareability implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
    ) {
    }
}
