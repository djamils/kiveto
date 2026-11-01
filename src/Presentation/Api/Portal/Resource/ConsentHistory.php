<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ConsentHistoryProvider;
use App\Presentation\Api\Portal\View\ConsentHistoryItemView;

#[ApiResource(
    shortName: 'ConsentHistory',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/consents/history',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Consentements'],
                summary: 'L\'historique des consentements de l\'appelant dans la clinique.',
                description: 'Ajout seul, du plus récent au plus ancien ; un changement appliqué à plusieurs fiches donne une seule ligne. `source: clinic` = saisie à la clinique, auteur « La clinique » ; `source: system` + `approximate: true` = reprise des consentements existants (date au plus tard).',
                responses: [
                    '200' => new Response(description: 'Une page de l\'historique.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de fiche liée active dans la clinique).'),
                ],
            ),
            provider: ConsentHistoryProvider::class,
            parameters: [
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                // No schema bounds: they would turn into a 422, while the query clamps the limit to [1, 50].
                'limit' => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page, ramenée entre 1 et 50 (20 par défaut).'),
            ],
        ),
    ],
)]
final readonly class ConsentHistory
{
    /**
     * @param list<ConsentHistoryItemView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
