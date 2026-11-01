<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\AcceptLinkSuggestionProcessor;
use App\Presentation\Api\Portal\View\LinkSuggestionAcceptedView;

#[ApiResource(
    shortName: 'LinkSuggestionAcceptance',
    operations: [
        new Post(
            uriTemplate: '/me/link-suggestions/{suggestionId}/accept',
            uriVariables: [
                'suggestionId' => new Link(fromClass: self::class, identifiers: []),
            ],
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Demande le rattachement à la clinique suggérée.',
                description: 'Ouvre un lien `pending` vers chaque dossier correspondant de la clinique ; la clinique le confirme ou le refuse dans VetApp. En attendant, `GET /me` liste la clinique avec `linkStatus: pending` et `linkedAt: null`, et les routes `/clinics/{clinicId}/…` répondent 404. Rejouer l\'acceptation d\'une demande en attente rend la même réponse.',
                responses: [
                    '202' => new Response(description: 'Demande transmise à la clinique (`linkStatus: pending`).'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (suggestion inconnue ou plus proposée).'),
                ],
            ),
            input: false,
            output: LinkSuggestionAcceptedView::class,
            read: false,
            deserialize: false,
            validate: false,
            processor: AcceptLinkSuggestionProcessor::class,
        ),
    ],
)]
final readonly class LinkSuggestionAcceptance
{
}
