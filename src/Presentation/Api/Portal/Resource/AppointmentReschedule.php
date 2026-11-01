<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RescheduleAppointmentProcessor;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AppointmentReschedule',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/appointments/{appointmentId}/reschedule',
            uriVariables: [
                'clinicId'      => new Link(fromClass: self::class, identifiers: []),
                'appointmentId' => new Link(fromClass: self::class, identifiers: []),
            ],
            status: 200,
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Déplace un rendez-vous pris en ligne.',
                description: 'Seulement un rendez-vous pris en ligne avec un motif, dans le délai d\'annulation. Le statut est conservé (une demande à confirmer reste à confirmer et repart pour un nouveau délai). Même créneau et même praticien : 200 sans effet.',
                responses: [
                    '200' => new Response(description: 'Le rendez-vous déplacé.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; rendez-vous inconnu ou d\'un autre client ; praticien non réservable pour le motif).'),
                    '409' => new Response(description: '`slot_taken` : recharger les créneaux.'),
                    '422' => new Response(description: '`booking_not_open`, `cancellation_window_closed`, `appointment_not_modifiable` ou `validation_failed`.'),
                ],
            ),
            output: AppointmentView::class,
            read: false,
            processor: RescheduleAppointmentProcessor::class,
        ),
    ],
)]
final class AppointmentReschedule
{
    /** A start offered by the availability, with its offset. */
    #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
    #[Assert\NotBlank(message: 'Créneau obligatoire.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Créneau invalide : date et heure ISO 8601 avec décalage attendues.')]
    public string $startAt;

    #[ApiProperty(schema: ['type' => 'string', 'format' => 'uuid'])]
    #[Assert\NotBlank(message: 'Praticien obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de praticien invalide.')]
    public string $practitionerId;
}
