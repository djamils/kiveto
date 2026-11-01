<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ListingPractitionersProvider;
use App\Presentation\Api\Portal\View\PractitionerView;
use Symfony\Component\Validator\Constraints as Assert;

/** A practitioner a new client may book online for the motif; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'ListingPractitioners',
    operations: [
        new GetCollection(
            uriTemplate: '/clinic-listings/{listingId}/booking/practitioners',
            uriVariables: ['listingId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'Les praticiens réservables en ligne par un nouveau client pour un motif.',
                description: 'Publique. Même forme que `/clinics/{clinicId}/booking/practitioners`.',
                responses: [
                    '200' => new Response(description: 'Les praticiens.'),
                    '404' => new Response(description: '`not_found` (clinique inconnue, non listée ou inactive ; motif inconnu, archivé, non ouvert en ligne ou aux nouveaux clients).'),
                    '422' => new Response(description: '`booking_not_open` ou `validation_failed`.'),
                    '429' => new Response(description: '`rate_limited`, avec `Retry-After`.'),
                ],
                security: [],
            ),
            output: PractitionerView::class,
            provider: ListingPractitionersProvider::class,
            parameters: [
                'motifId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Motif obligatoire.')]),
            ],
        ),
    ],
)]
final readonly class ListingPractitioners
{
}
