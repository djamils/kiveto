<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/**
 * The whole record of a consultation; the page and its cockpit both read it,
 * once for the request.
 */
final readonly class GetConsultationDetails implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
    ) {
    }
}
