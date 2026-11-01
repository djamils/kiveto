<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\OwnerRequestListProvider;
use App\Presentation\Api\Portal\View\PortalRequestView;

#[ApiResource(
    shortName: 'OwnerRequestList',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/requests',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Demandes'],
                summary: 'Les demandes de modification et de renouvellement du propriétaire dans cette clinique.',
                description: 'De la plus récente à la plus ancienne. `closed` = close sans traitement (lien révoqué, client archivé, ordonnance annulée, animal archivé ou décédé). Pas d\'annulation ni de modification d\'une demande envoyée en v1.',
                responses: [
                    '200' => new Response(description: 'Une page de demandes.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique).'),
                ],
            ),
            provider: OwnerRequestListProvider::class,
            parameters: [
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                'limit'  => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page : 20 par défaut, ramenée à 50 au plus.'),
            ],
        ),
    ],
)]
final readonly class OwnerRequestList
{
    /**
     * @param list<PortalRequestView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
