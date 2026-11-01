<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\BookingRequestsProvider;
use App\Presentation\Api\Portal\View\BookingRequestView;

#[ApiResource(
    shortName: 'BookingRequests',
    operations: [
        new Get(
            uriTemplate: '/me/booking-requests',
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Les demandes de rendez-vous du compte auprès de cliniques de l\'annuaire.',
                description: 'Toutes cliniques, de la plus récente à la plus ancienne. Une demande confirmée donne `appointmentId` : le rendez-vous se lit alors sous `/clinics/{c}/appointments`. Une demande refusée donne `declineReason`.',
                responses: [
                    '200' => new Response(description: 'Une page de demandes.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                ],
            ),
            provider: BookingRequestsProvider::class,
            parameters: [
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                'limit'  => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page : 20 par défaut, ramenée à 50 au plus.'),
            ],
        ),
    ],
)]
final readonly class BookingRequests
{
    /**
     * @param list<BookingRequestView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
