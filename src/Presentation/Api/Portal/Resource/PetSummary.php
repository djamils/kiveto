<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Presentation\Api\Portal\State\PetListProvider;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * A pet as listed, the domain values mapped to the contract (S3i). The list
 * answers a plain JSON array of it; the home embeds it too.
 */
#[ApiResource(
    shortName: 'PetSummary',
    operations: [
        new GetCollection(
            uriTemplate: '/clinics/{clinicId}/pets',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Animaux'],
                summary: 'Les animaux du propriétaire dans cette clinique.',
                description: 'Décédés et disparus compris (`lifeStatus`). Vivants d\'abord, puis par nom.',
                responses: [
                    '200' => new Response(description: 'Les animaux.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique).'),
                ],
            ),
            provider: PetListProvider::class,
        ),
    ],
)]
final readonly class PetSummary
{
    public const string MIXED_BREED = 'Croisé';

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
    ) {
    }

    public static function of(OwnerPet $pet): self
    {
        return new self(
            $pet->id,
            $pet->name,
            $pet->species,
            self::breedOf($pet),
            $pet->sex,
            $pet->reproductiveStatus,
            ApiDate::nullableDate($pet->birthDate),
            self::initialOf($pet->name),
            $pet->lifeStatus,
        );
    }

    /** The breed, else « Croisé » for a mixed breed, else null. */
    public static function breedOf(OwnerPet $pet): ?string
    {
        $breed = null === $pet->breedName ? '' : mb_trim($pet->breedName);

        if ('' !== $breed) {
            return $breed;
        }

        return $pet->isMixedBreed ? self::MIXED_BREED : null;
    }

    public static function initialOf(string $name): string
    {
        return mb_strtoupper(mb_substr(mb_trim($name), 0, 1));
    }
}
