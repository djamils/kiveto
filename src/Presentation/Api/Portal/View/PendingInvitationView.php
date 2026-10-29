<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** An invitation waiting for the owner of the proven phone. */
final readonly class PendingInvitationView
{
    /** Its OpenAPI schema, inlined by the views that list invitations. */
    public const array SCHEMA = [
        'type'       => 'object',
        'required'   => ['invitationId', 'clinic'],
        'properties' => [
            'invitationId' => ['type' => 'string', 'format' => 'uuid'],
            'clinic'       => [
                'type'       => 'object',
                'required'   => ['name', 'city'],
                'properties' => ['name' => ['type' => 'string'], 'city' => ['type' => ['string', 'null']]],
            ],
        ],
    ];

    public function __construct(
        public string $invitationId,
        public ClinicSummaryView $clinic,
    ) {
    }
}
