<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** The answer of `POST /me/link-suggestions/{id}/accept`: the clinic has to confirm. */
final readonly class LinkSuggestionAcceptedView
{
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pending']])]
        public string $linkStatus,
    ) {
    }
}
