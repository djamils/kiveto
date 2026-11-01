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
use App\Presentation\Api\Portal\State\PetChangeRequestProcessor;
use App\Presentation\Api\Portal\View\PortalRequestCreatedView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'PetChangeRequestCreation',
    operations: [
        new Post(
            uriTemplate: '/clinics/{clinicId}/pets/{petId}/change-requests',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
                'petId'    => new Link(fromClass: self::class, identifiers: []),
            ],
            status: 202,
            openapi: new Operation(
                tags: ['Demandes'],
                summary: 'Demande à la clinique de modifier une information de l\'animal.',
                description: 'La clinique applique la modification à la main ; l\'animal ne change pas avant. Règles par champ : `name` 1 à 60 caractères ; `weight` décimal (virgule ou point) de 0,05 à 150 kg, `measuredOn` facultatif (pas dans le futur, par défaut le jour de la demande) ; `sterilization` `neutered` ou `intact` ; `chip_number` 15 chiffres ; `other` exige `note`. 10 demandes (modification ou renouvellement) par 24 h et par clinique, et 3 demandes ouvertes au plus par animal et par champ. Pas d\'annulation ni de modification d\'une demande envoyée. Rejouer la requête avec la même clé Idempotency-Key rend la même réponse sans seconde demande.',
                responses: [
                    '202' => new Response(description: 'La demande envoyée à la clinique.'),
                    '400' => new Response(description: '`idempotency_key_missing`.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; animal inconnu ou d\'un autre client).'),
                    '422' => new Response(description: '`pet_not_eligible` (animal décédé, disparu ou archivé), `idempotency_key_reused` ou `validation_failed`.'),
                    '429' => new Response(description: '`rate_limited` (`Retry-After` pour le quota de 24 h).'),
                ],
                parameters: [
                    new Parameter('Idempotency-Key', 'header', 'Clé aléatoire (UUID) propre à cette demande, réutilisée pour ses nouvelles tentatives.', true, schema: ['type' => 'string', 'maxLength' => 128]),
                ],
            ),
            output: PortalRequestCreatedView::class,
            read: false,
            processor: PetChangeRequestProcessor::class,
            extraProperties: ['idempotent' => true],
        ),
    ],
)]
final class PetChangeRequestCreation
{
    public const array FIELDS = ['name', 'weight', 'sterilization', 'chip_number', 'other'];

    #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => self::FIELDS])]
    #[Assert\NotBlank(message: 'Champ à modifier obligatoire.')]
    #[Assert\Choice(choices: self::FIELDS, message: 'Champ inconnu : name, weight, sterilization, chip_number ou other.')]
    public ?string $field = null;

    #[ApiProperty(required: true, schema: ['type' => 'string', 'maxLength' => 120])]
    #[Assert\NotNull(message: 'Valeur proposée obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'La valeur proposée comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $proposedValue = null;

    /** The weighing day of a `weight` request. */
    #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date'])]
    #[Assert\Date(message: 'Date de pesée invalide : AAAA-MM-JJ attendu.')]
    public ?string $measuredOn = null;

    #[ApiProperty(schema: ['type' => ['string', 'null'], 'maxLength' => 500])]
    #[Assert\Length(max: 500, maxMessage: 'La note comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $note = null;
}
