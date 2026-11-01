<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\AppointmentListProvider;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AppointmentList',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/appointments',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Les rendez-vous du propriétaire dans cette clinique.',
                description: 'Rendez-vous des fiches reliées et de leurs animaux principaux. `upcoming` (par défaut) = rendez-vous qui occupent un créneau et commencent maintenant ou plus tard, du plus proche au plus lointain ; `past` = tous les autres, du plus récent au plus ancien.',
                responses: [
                    '200' => new Response(description: 'Une page de rendez-vous.'),
                    '400' => new Response(description: '`cursor_invalid`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique).'),
                    '422' => new Response(description: '`validation_failed`.'),
                ],
            ),
            provider: AppointmentListProvider::class,
            parameters: [
                'scope'  => new QueryParameter(schema: ['type' => 'string', 'enum' => self::SCOPES, 'default' => 'upcoming'], constraints: [new Assert\Choice(choices: self::SCOPES, message: 'Portée inconnue.')]),
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                'limit'  => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page : 20 par défaut, ramenée à 50 au plus.'),
            ],
        ),
    ],
)]
final readonly class AppointmentList
{
    public const array SCOPES = ['upcoming', 'past'];

    /**
     * @param list<AppointmentView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
