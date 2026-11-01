<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ClinicListingsProvider;
use App\Presentation\Api\Portal\View\ClinicListingSummaryView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ClinicListings',
    operations: [
        new Get(
            uriTemplate: '/clinic-listings',
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'Rechercher une clinique de l\'annuaire.',
                description: 'Publique. Seules les cliniques Kiveto actives qui ont choisi de figurer dans l\'annuaire. `q` (2 caractères au moins) cherche dans le nom et la ville. Avec `lat` et `lng` (ensemble) : tri par distance (150 km au plus), les cliniques sans coordonnées sont absentes de ce résultat seulement ; sinon tri par nom. Le curseur est lié à l\'ordre et aux coordonnées de la recherche. `onlineBooking` est calculé en direct. `nextAvailableAt` est toujours `null` en v1.',
                responses: [
                    '200' => new Response(description: 'Une page de cliniques.'),
                    '400' => new Response(description: '`cursor_invalid` (illisible, ou émis pour d\'autres coordonnées).'),
                    '422' => new Response(description: '`validation_failed` (`q`, `lat`, `lng`).'),
                    '429' => new Response(description: '`rate_limited` (120 requêtes par minute et par adresse IP), avec `Retry-After`.'),
                ],
                security: [],
            ),
            provider: ClinicListingsProvider::class,
            parameters: [
                'q'      => new QueryParameter(schema: ['type' => 'string'], description: 'Nom ou ville, 2 caractères au moins.'),
                'lat'    => new QueryParameter(schema: ['type' => 'number'], description: 'Latitude, de -90 à 90, avec `lng`.', constraints: [new Assert\Regex(pattern: self::DECIMAL, message: 'La latitude doit être un nombre.')]),
                'lng'    => new QueryParameter(schema: ['type' => 'number'], description: 'Longitude, de -180 à 180, avec `lat`.', constraints: [new Assert\Regex(pattern: self::DECIMAL, message: 'La longitude doit être un nombre.')]),
                'cursor' => new QueryParameter(schema: ['type' => 'string']),
                'limit'  => new QueryParameter(schema: ['type' => 'integer'], description: 'Taille de page : 20 par défaut, ramenée à 50 au plus.'),
            ],
        ),
    ],
)]
final readonly class ClinicListings
{
    private const string DECIMAL = '/^-?\d{1,3}(\.\d{1,12})?$/';

    /**
     * @param list<ClinicListingSummaryView> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
