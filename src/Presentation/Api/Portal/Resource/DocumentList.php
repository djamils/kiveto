<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\DocumentListProvider;
use App\Presentation\Api\Portal\View\DocumentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'DocumentList',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/documents',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Documents'],
                summary: 'Les documents publiés pour le propriétaire dans cette clinique.',
                description: 'Du plus récent au plus ancien. Statut de paiement et état des ordonnances lus en direct.',
                responses: [
                    '200' => new Response(description: 'Une page de documents.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique).'),
                    '422' => new Response(description: '`validation_failed`.'),
                ],
            ),
            provider: DocumentListProvider::class,
            parameters: [
                'type'   => new QueryParameter(schema: ['type' => 'string', 'enum' => self::TYPES], constraints: [new Assert\Choice(choices: self::TYPES, message: 'Type de document inconnu.')]),
                'petId'  => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], constraints: [new Assert\Uuid(message: 'Identifiant d\'animal invalide.')]),
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                'limit'  => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]),
            ],
        ),
    ],
)]
final readonly class DocumentList
{
    public const array TYPES = ['report', 'prescription', 'invoice', 'lab_result', 'certificate'];

    /**
     * @param list<DocumentView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
