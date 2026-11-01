<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** The answer of `POST …/renewal-requests`: the open request of the prescription. */
final readonly class RenewalRequestCreatedView
{
    public function __construct(
        public string $requestId,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['submitted']])]
        public string $status = 'submitted',
    ) {
    }
}
