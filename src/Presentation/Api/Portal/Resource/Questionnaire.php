<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\QuestionnaireProvider;
use App\Presentation\Api\Portal\View\QuestionnaireView;

/** The pre-visit questionnaire of an appointment of the owner (QuestionnaireView). */
#[ApiResource(
    shortName: 'Questionnaire',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/appointments/{appointmentId}/questionnaire',
            uriVariables: [
                'clinicId'      => new Link(fromClass: self::class, identifiers: []),
                'appointmentId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Demandes'],
                summary: 'Le questionnaire pré-visite d\'un rendez-vous.',
                description: 'Version 1. `status` : `not_started` (sans `answers` ni `updatedAt`) ou `submitted`. `editable` = modifiable par `PUT` : le rendez-vous occupe son créneau, n\'a pas commencé et aucune consultation n\'a démarré.',
                responses: [
                    '200' => new Response(description: 'Le questionnaire.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; rendez-vous inconnu, d\'un autre client, annulé ou refusé).'),
                ],
            ),
            output: QuestionnaireView::class,
            provider: QuestionnaireProvider::class,
        ),
    ],
)]
final readonly class Questionnaire
{
}
