<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\LinkSuggestionsProvider;
use App\Presentation\Api\Portal\View\LinkSuggestionView;

/** The clinics the account may ask to be linked to; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'LinkSuggestions',
    operations: [
        new GetCollection(
            uriTemplate: '/me/link-suggestions',
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Les cliniques qui ont un dossier client avec le numéro du compte.',
                description: 'Une suggestion par clinique (les dossiers d\'une même famille sont regroupés), par nom de clinique. Seuls le nom et la ville de la clinique sont exposés, jamais un dossier client ; `id` est opaque. Le numéro doit être le mobile (`mobile`) d\'un dossier non archivé d\'une clinique active. Une clinique disparaît dès que le compte a un lien (quel que soit son statut) avec chacun de ses dossiers correspondants : une suggestion refusée par la clinique ne revient pas.',
                responses: [
                    '200' => new Response(description: 'Les suggestions (liste vide s\'il n\'y en a pas).'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                ],
            ),
            output: LinkSuggestionView::class,
            provider: LinkSuggestionsProvider::class,
        ),
    ],
)]
final readonly class LinkSuggestions
{
}
