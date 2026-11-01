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
use App\Presentation\Api\Portal\State\BookAppointmentProcessor;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AppointmentBooking',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/appointments',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            status: 201,
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Réserve un créneau en ligne pour un animal du propriétaire.',
                description: '`status` = `confirmed` ou `pending_clinic_confirmation` selon le motif, `source: portal`. Rejouer la requête avec la même clé Idempotency-Key rend la même réponse sans second rendez-vous.',
                responses: [
                    '201' => new Response(description: 'Le rendez-vous pris.'),
                    '400' => new Response(description: '`idempotency_key_missing`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; animal d\'un autre client, vendu ou donné ; motif inconnu).'),
                    '409' => new Response(description: '`slot_taken` : recharger les créneaux.'),
                    '422' => new Response(description: '`pet_not_bookable`, `booking_not_open`, `idempotency_key_reused` ou `validation_failed`.'),
                ],
                parameters: [
                    new Parameter('Idempotency-Key', 'header', 'Clé aléatoire (UUID) propre à cette réservation, réutilisée pour ses nouvelles tentatives.', true, schema: ['type' => 'string', 'maxLength' => 128]),
                ],
            ),
            output: AppointmentView::class,
            read: false,
            processor: BookAppointmentProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
    ],
)]
final class AppointmentBooking
{
    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Animal obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant d\'animal invalide.')]
    public string $petId;

    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Motif obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de motif invalide.')]
    public string $motifId;

    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Praticien obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de praticien invalide.')]
    public string $practitionerId;

    /** A start offered by the availability, with its offset. */
    #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
    #[Assert\NotBlank(message: 'Créneau obligatoire.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Créneau invalide : date et heure ISO 8601 avec décalage attendues.')]
    public string $startAt;
}
