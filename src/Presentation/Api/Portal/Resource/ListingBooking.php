<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\Input\NewPet;
use App\Presentation\Api\Portal\State\ListingBookingProcessor;
use App\Presentation\Api\Portal\View\BookingRequestView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ListingBooking',
    operations: [
        new Post(
            uriTemplate: '/clinic-listings/{listingId}/booking/appointments',
            uriVariables: ['listingId' => new Link(fromClass: self::class, identifiers: [])],
            status: 201,
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'Demande un premier rendez-vous à une clinique de l\'annuaire, en nouveau client.',
                description: 'Authentifié (seule route non-GET sous `/clinic-listings`). Le créneau est réservé en attente de confirmation par la clinique (toujours manuelle pour un nouveau client) ; aucun lien avec la clinique n\'est créé avant qu\'elle confirme. Au plus 1 demande en attente par clinique et 3 en tout ; 10 demandes par 24 h. La demande se suit avec `GET /me/booking-requests`.',
                responses: [
                    '201' => new Response(description: 'La demande, `status: pending`.'),
                    '400' => new Response(description: '`idempotency_key_missing`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (fiche inconnue, non publiée ou inactive ; motif ou praticien inconnu).'),
                    '409' => new Response(description: '`slot_taken` : recharger les créneaux.'),
                    '422' => new Response(description: '`already_linked`, `booking_request_limit`, `booking_not_open`, `idempotency_key_reused` ou `validation_failed`.'),
                    '429' => new Response(description: '`rate_limited` (en-tête `Retry-After`).'),
                ],
                parameters: [
                    new Parameter('Idempotency-Key', 'header', 'Clé aléatoire (UUID) propre à cette demande, réutilisée pour ses nouvelles tentatives.', true, schema: ['type' => 'string', 'maxLength' => 128]),
                ],
            ),
            output: BookingRequestView::class,
            read: false,
            processor: ListingBookingProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
    ],
)]
final class ListingBooking
{
    #[Assert\NotNull(message: 'Animal obligatoire.')]
    #[Assert\Valid]
    public NewPet $newPet;

    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Motif obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de motif invalide.')]
    public string $motifId;

    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Praticien obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de praticien invalide.')]
    public string $practitionerId;

    /** A start offered by the listing's availability, with its offset. */
    #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
    #[Assert\NotBlank(message: 'Créneau obligatoire.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Créneau invalide : date et heure ISO 8601 avec décalage attendues.')]
    public string $startAt;
}
