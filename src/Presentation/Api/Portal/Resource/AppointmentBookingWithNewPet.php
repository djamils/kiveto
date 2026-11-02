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
use App\Presentation\Api\Portal\State\BookAppointmentWithNewPetProcessor;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AppointmentBookingWithNewPet',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/appointments/with-new-pet',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            status: 201,
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Réserve un créneau en ligne pour un animal que la clinique ne connaît pas encore.',
                description: 'Crée l\'animal du propriétaire (sexe et stérilisation inconnus) dans la même transaction que le rendez-vous : en cas de `slot_taken`, aucun animal ne reste. Seule une nouvelle tentative de la même requête (même Idempotency-Key) réutilise l\'animal qu\'elle a créé ; un animal créé par la clinique n\'est jamais réutilisé. Archivé automatiquement si la demande est refusée ou expire avant tout acte clinique.',
                responses: [
                    '201' => new Response(description: 'Le rendez-vous pris.'),
                    '400' => new Response(description: '`idempotency_key_missing`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; motif inconnu).'),
                    '409' => new Response(description: '`slot_taken` : recharger les créneaux.'),
                    '422' => new Response(description: '`booking_not_open`, `idempotency_key_reused` ou `validation_failed`.'),
                ],
                parameters: [
                    new Parameter('Idempotency-Key', 'header', 'Clé aléatoire (UUID) propre à cette réservation, réutilisée pour ses nouvelles tentatives.', true, schema: ['type' => 'string', 'maxLength' => 128]),
                ],
            ),
            output: AppointmentView::class,
            read: false,
            processor: BookAppointmentWithNewPetProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
    ],
)]
final class AppointmentBookingWithNewPet
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

    /** A start offered by the availability, with its offset. */
    #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
    #[Assert\NotBlank(message: 'Créneau obligatoire.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Créneau invalide : date et heure ISO 8601 avec décalage attendues.')]
    public string $startAt;
}
