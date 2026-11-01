<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ClinicListingDetailProvider;
use App\Presentation\Api\Portal\View\ListingAddressView;
use App\Presentation\Api\Portal\View\OpeningHoursView;

/** A clinic of the directory, in full; `id` is the listing id, never the clinic's. */
#[ApiResource(
    shortName: 'ClinicListingDetail',
    operations: [
        new Get(
            uriTemplate: '/clinic-listings/{listingId}',
            uriVariables: ['listingId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Annuaire'],
                summary: 'La fiche d\'une clinique de l\'annuaire.',
                description: 'Publique (servira les pages web indexables). `openingHours` : les plages de la semaine type, par jour ISO (1 = lundi) puis heure d\'ouverture, en heure locale de la clinique (`timezone`) ; les fermetures exceptionnelles n\'y figurent pas.',
                responses: [
                    '200' => new Response(description: 'La clinique.'),
                    '404' => new Response(description: '`not_found` (inconnue, non listée ou inactive).'),
                    '429' => new Response(description: '`rate_limited`, avec `Retry-After`.'),
                ],
                security: [],
            ),
            provider: ClinicListingDetailProvider::class,
        ),
    ],
)]
final readonly class ClinicListingDetail
{
    /**
     * @param list<OpeningHoursView> $openingHours
     * @param list<string>           $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?ListingAddressView $address,
        public ?string $phone,
        /** IANA time zone (« Europe/Paris »). */
        public string $timezone,
        public array $openingHours,
        public bool $acceptsNewClients,
        /** Open to new clients online: booking enabled, new clients accepted, a motif open to them. */
        public bool $onlineBooking,
        public array $tags,
    ) {
    }
}
