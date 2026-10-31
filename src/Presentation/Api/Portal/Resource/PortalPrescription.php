<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\PortalPrescriptionProvider;
use App\Presentation\Api\Portal\View\PetReferenceView;
use App\Presentation\Api\Portal\View\PrescriptionLineView;

#[ApiResource(
    shortName: 'Prescription',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/prescriptions/{prescriptionId}',
            uriVariables: [
                'clinicId'       => new Link(fromClass: self::class, identifiers: []),
                'prescriptionId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Documents'],
                summary: 'Une ordonnance publiée et ses lignes.',
                responses: [
                    '200' => new Response(description: 'L\'ordonnance.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (non publiée, retirée ou d\'un autre client).'),
                ],
            ),
            provider: PortalPrescriptionProvider::class,
        ),
    ],
)]
final readonly class PortalPrescription
{
    /**
     * @param list<PrescriptionLineView> $lines
     */
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $issuedAt,
        public string $vet,
        public PetReferenceView $pet,
        public array $lines,
    ) {
    }
}
