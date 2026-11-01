<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ListingMotifsProvider;
use App\Presentation\Api\Portal\View\BookingMotifView;

/** A motif a new client may book online; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'ListingMotifs',
    operations: [
        new GetCollection(
            uriTemplate: '/clinic-listings/{listingId}/booking/motifs',
            uriVariables: ['listingId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'Les motifs réservables en ligne par un nouveau client.',
                description: 'Publique. Même forme que `/clinics/{clinicId}/booking/motifs`, dans l\'ordre de la clinique : seuls les motifs ouverts en ligne aux nouveaux clients. Sans animal, `suggested` est toujours `false`.',
                responses: [
                    '200' => new Response(description: 'Les motifs.'),
                    '404' => new Response(description: '`not_found` (clinique inconnue, non listée ou inactive).'),
                    '422' => new Response(description: '`booking_not_open` (la clinique ne prend pas de nouveaux clients en ligne).'),
                    '429' => new Response(description: '`rate_limited`, avec `Retry-After`.'),
                ],
                security: [],
            ),
            output: BookingMotifView::class,
            provider: ListingMotifsProvider::class,
        ),
    ],
)]
final readonly class ListingMotifs
{
}
