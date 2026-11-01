<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\AppointmentProvider;
use App\Presentation\Api\Portal\View\AppointmentView;

/** An appointment of the owner (AppointmentView). */
#[ApiResource(
    shortName: 'Appointment',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/appointments/{appointmentId}',
            uriVariables: [
                'clinicId'      => new Link(fromClass: self::class, identifiers: []),
                'appointmentId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Un rendez-vous du propriétaire.',
                description: '`relatedCareItem` = le soin de l\'animal du type de soin du motif, sinon `null` (avertissement de l\'écran 16).',
                responses: [
                    '200' => new Response(description: 'Le rendez-vous.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé, ou rendez-vous inconnu ou d\'un autre client).'),
                ],
            ),
            output: AppointmentView::class,
            provider: AppointmentProvider::class,
        ),
    ],
)]
final readonly class Appointment
{
}
