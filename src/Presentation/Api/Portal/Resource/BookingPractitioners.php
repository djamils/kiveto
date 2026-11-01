<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\BookingPractitionersProvider;
use App\Presentation\Api\Portal\View\PractitionerView;
use Symfony\Component\Validator\Constraints as Assert;

/** A practitioner bookable online for the motif; the route answers a plain JSON array of them. */
#[ApiResource(
    shortName: 'BookingPractitioners',
    operations: [
        new GetCollection(
            uriTemplate: '/clinics/{clinicId}/booking/practitioners',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Rendez-vous'],
                summary: 'Les praticiens réservables en ligne pour un motif.',
                description: 'Par nom. Un praticien qui a quitté la clinique depuis l\'enregistrement du motif n\'apparaît plus.',
                responses: [
                    '200' => new Response(description: 'Les praticiens.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; motif inconnu, archivé ou non ouvert en ligne).'),
                    '422' => new Response(description: '`booking_not_open` ou `validation_failed`.'),
                ],
            ),
            output: PractitionerView::class,
            provider: BookingPractitionersProvider::class,
            parameters: [
                'motifId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: true, constraints: [new Assert\NotBlank(message: 'Motif obligatoire.')]),
            ],
        ),
    ],
)]
final readonly class BookingPractitioners
{
}
