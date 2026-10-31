<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\PetProvider;
use App\Presentation\Api\Portal\View\PetWeightView;

/** The listed shape of a pet, plus its latest weight, masked chip and referent vet. */
#[ApiResource(
    shortName: 'Pet',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/pets/{petId}',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
                'petId'    => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Animaux'],
                summary: 'La fiche d\'un animal.',
                description: '`lastWeight` = pesée la plus récente entre la fiche animal et les consultations clôturées (à date égale, la fiche l\'emporte). `referentVet` = vétérinaire du dernier compte rendu partagé avec l\'appelant.',
                responses: [
                    '200' => new Response(description: 'L\'animal.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé, ou animal archivé, en copropriété secondaire, vendu, donné ou d\'un autre client).'),
                ],
            ),
            provider: PetProvider::class,
        ),
    ],
)]
final readonly class Pet
{
    public function __construct(
        public string $id,
        public string $name,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['dog', 'cat', 'nac', 'other']])]
        public string $species,
        public ?string $breed,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['male', 'female', 'unknown']])]
        public string $sex,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['intact', 'neutered', 'unknown']])]
        public string $sterilization,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $birthDate,
        public string $initial,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['alive', 'deceased', 'missing']])]
        public string $lifeStatus,
        public ?PetWeightView $lastWeight,
        public ?string $chipNumberMasked,
        public ?string $referentVet,
    ) {
    }
}
