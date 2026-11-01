<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\BookingMotifsProvider;
use App\Presentation\Api\Portal\View\BookingMotifView;
use Symfony\Component\Validator\Constraints as Assert;

/** A motif the owner may book online; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'BookingMotifs',
    operations: [
        new GetCollection(
            uriTemplate: '/clinics/{clinicId}/booking/motifs',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Les motifs de rendez-vous réservables en ligne.',
                description: 'Dans l\'ordre de la clinique. Avec `petId`, un motif est `suggested` quand l\'animal a un soin en retard ou à prévoir du type de soin du motif (`suggestionReason` : « Rappel de vaccin en retard », « Rappel de vaccin à prévoir »…).',
                responses: [
                    '200' => new Response(description: 'Les motifs.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique, ou animal non visible).'),
                    '422' => new Response(description: '`booking_not_open` ou `validation_failed`.'),
                ],
            ),
            output: BookingMotifView::class,
            provider: BookingMotifsProvider::class,
            parameters: [
                'petId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], constraints: [new Assert\Uuid(message: 'Identifiant d\'animal invalide.')]),
            ],
        ),
    ],
)]
final readonly class BookingMotifs
{
}
