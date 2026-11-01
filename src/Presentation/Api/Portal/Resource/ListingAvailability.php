<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ListingAvailabilityProvider;
use App\Presentation\Api\Portal\View\AvailabilityDayView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ListingAvailability',
    operations: [
        new Get(
            uriTemplate: '/clinic-listings/{listingId}/booking/availability',
            uriVariables: ['listingId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'Les créneaux réservables en ligne par un nouveau client.',
                description: 'Publique. Même forme et mêmes règles que `/clinics/{clinicId}/booking/availability`, mais un nouveau client est toujours validé par la clinique : seuls les créneaux au-delà du délai de confirmation sont proposés.',
                responses: [
                    '200' => new Response(description: 'Les jours et leurs créneaux.'),
                    '404' => new Response(description: '`not_found` (clinique inconnue, non listée ou inactive ; motif inconnu ou non ouvert aux nouveaux clients ; praticien non réservable pour ce motif).'),
                    '422' => new Response(description: '`booking_not_open` ou `validation_failed` (intervalle).'),
                    '429' => new Response(description: '`rate_limited`, avec `Retry-After`.'),
                ],
                security: [],
            ),
            provider: ListingAvailabilityProvider::class,
            parameters: [
                'motifId'        => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Motif obligatoire.')]),
                'practitionerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Praticien obligatoire.')]),
                'from'           => new QueryParameter(schema: ['type' => 'string', 'format' => 'date'], required: true, constraints: [new Assert\NotBlank(message: 'Date de début obligatoire.')]),
                'to'             => new QueryParameter(schema: ['type' => 'string', 'format' => 'date'], required: true, constraints: [new Assert\NotBlank(message: 'Date de fin obligatoire.')]),
            ],
        ),
    ],
)]
final readonly class ListingAvailability
{
    /**
     * @param list<AvailabilityDayView> $days
     */
    public function __construct(
        public array $days,
    ) {
    }
}
