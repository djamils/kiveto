<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\CancelAppointmentProcessor;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AppointmentCancellation',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/appointments/{appointmentId}/cancel',
            uriVariables: [
                'clinicId'      => new Link(fromClass: self::class, identifiers: []),
                'appointmentId' => new Link(fromClass: self::class, identifiers: []),
            ],
            status: 200,
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Annule un rendez-vous dans le délai d\'annulation.',
                description: 'Naturellement idempotente : un rendez-vous déjà annulé par le client rend 200 avec le même corps.',
                responses: [
                    '200' => new Response(description: 'Le rendez-vous annulé (`status: cancelled_by_client`).'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; rendez-vous inconnu ou d\'un autre client).'),
                    '422' => new Response(description: '`cancellation_window_closed`, `appointment_not_modifiable` (refusé, terminé, non honoré, annulé par la clinique) ou `validation_failed`.'),
                ],
            ),
            output: AppointmentView::class,
            read: false,
            processor: CancelAppointmentProcessor::class,
        ),
    ],
)]
final class AppointmentCancellation
{
    public const array REASONS = ['unavailable', 'no_longer_needed', 'seen_elsewhere', 'other'];

    #[ApiProperty(schema: ['type' => 'string', 'enum' => self::REASONS])]
    #[Assert\NotBlank(message: 'Motif d\'annulation obligatoire.')]
    #[Assert\Choice(choices: self::REASONS, message: 'Motif d\'annulation inconnu.')]
    public string $reason;
}
