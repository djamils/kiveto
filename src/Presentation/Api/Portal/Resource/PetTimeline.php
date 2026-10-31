<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\PetTimelineProvider;
use App\Presentation\Api\Portal\View\TimelineEntryView;

#[ApiResource(
    shortName: 'PetTimeline',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/pets/{petId}/timeline',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
                'petId'    => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Animaux'],
                summary: 'Les visites d\'un animal dont le compte rendu a été partagé avec l\'appelant.',
                description: 'Par date de clôture décroissante. `date` = début de la consultation, avec le décalage de la clinique ; `vet` = nom figé au partage.',
                responses: [
                    '200' => new Response(description: 'Une page de visites.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé, ou animal non visible).'),
                ],
            ),
            provider: PetTimelineProvider::class,
            parameters: [
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                // No schema bounds: they would turn into a 422, while the query clamps the limit to [1, 50].
                'limit' => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page, ramenée entre 1 et 50 (20 par défaut).'),
            ],
        ),
    ],
)]
final readonly class PetTimeline
{
    /**
     * @param list<TimelineEntryView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
