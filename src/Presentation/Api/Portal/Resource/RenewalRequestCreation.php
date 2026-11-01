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
use App\Presentation\Api\Portal\State\RenewalRequestProcessor;
use App\Presentation\Api\Portal\View\RenewalRequestCreatedView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'RenewalRequestCreation',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/prescriptions/{prescriptionId}/renewal-requests',
            uriVariables: [
                'clinicId'       => new Link(fromClass: self::class, identifiers: []),
                'prescriptionId' => new Link(fromClass: self::class, identifiers: []),
            ],
            status: 202,
            openapi: new Operation(
                tags: ['Demandes'],
                summary: 'Demande le renouvellement d\'une ordonnance.',
                description: 'Porte sur toute l\'ordonnance ; la clinique rédige au besoin une nouvelle ordonnance, liée à la demande (`renewalStatus` de la liste des documents). Une seconde demande alors qu\'une est ouverte rend 202 avec la même `{ requestId, status }`. 10 demandes (modification ou renouvellement) par 24 h et par clinique. Pas d\'annulation d\'une demande envoyée. Rejouer la requête avec la même clé Idempotency-Key rend la même réponse.',
                responses: [
                    '202' => new Response(description: 'La demande ouverte de l\'ordonnance.'),
                    '400' => new Response(description: '`idempotency_key_missing`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; ordonnance inconnue ou d\'un autre client).'),
                    '422' => new Response(description: '`prescription_not_renewable` (non émise, substance contrôlée, animal non vivant, refus de moins de 7 jours), `idempotency_key_reused` ou `validation_failed`.'),
                    '429' => new Response(description: '`rate_limited` (`Retry-After`).'),
                ],
                parameters: [
                    new Parameter('Idempotency-Key', 'header', 'Clé aléatoire (UUID) propre à cette demande, réutilisée pour ses nouvelles tentatives.', true, schema: ['type' => 'string', 'maxLength' => 128]),
                ],
            ),
            output: RenewalRequestCreatedView::class,
            read: false,
            processor: RenewalRequestProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
    ],
)]
final class RenewalRequestCreation
{
    public const array CONDITIONS = ['improved', 'no_improvement', 'adverse_effects'];

    #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => self::CONDITIONS])]
    #[Assert\NotBlank(message: 'État de l\'animal obligatoire.')]
    #[Assert\Choice(choices: self::CONDITIONS, message: 'État inconnu : improved, no_improvement ou adverse_effects.')]
    public ?string $petCondition = null;

    #[ApiProperty(schema: ['type' => ['string', 'null'], 'maxLength' => 500])]
    #[Assert\Length(max: 500, maxMessage: 'La note comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $note = null;
}
