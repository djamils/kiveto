<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\BookingAvailabilityProvider;
use App\Presentation\Api\Portal\View\AvailabilityDayView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BookingAvailability',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/booking/availability',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Les créneaux réservables en ligne d\'un praticien pour un motif.',
                description: 'Un jour par date demandée (dates locales de la clinique, incluses, 14 jours au plus, `from` ≥ aujourd\'hui local). `open` = jour d\'ouverture, hors fermeture exceptionnelle, dans l\'horizon. Créneaux émis avec le décalage de la clinique.',
                responses: [
                    '200' => new Response(description: 'Les jours et leurs créneaux.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; motif inconnu, ou praticien non réservable pour ce motif).'),
                    '422' => new Response(description: '`booking_not_open` (réservation désactivée, clinique non active) ou `validation_failed` (intervalle).'),
                ],
            ),
            provider: BookingAvailabilityProvider::class,
            parameters: [
                'motifId'        => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Motif obligatoire.')]),
                'practitionerId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Praticien obligatoire.')]),
                'from'           => new QueryParameter(schema: ['type' => 'string', 'format' => 'date'], required: true, constraints: [new Assert\NotBlank(message: 'Date de début obligatoire.')]),
                'to'             => new QueryParameter(schema: ['type' => 'string', 'format' => 'date'], required: true, constraints: [new Assert\NotBlank(message: 'Date de fin obligatoire.')]),
            ],
        ),
    ],
)]
final readonly class BookingAvailability
{
    /**
     * @param list<AvailabilityDayView> $days
     */
    public function __construct(
        public array $days,
    ) {
    }
}
