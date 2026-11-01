<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests\OwnerPortalRequest;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * A change or renewal request of the owner. `field` and `proposedValue` are
 * set for a `pet_change`, `prescriptionId` for a `prescription_renewal`;
 * instants carry the clinic's offset.
 */
final readonly class PortalRequestView
{
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pet_change', 'prescription_renewal']])]
        public string $kind,
        public string $petId,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'enum' => ['name', 'weight', 'sterilization', 'chip_number', 'other', null]])]
        public ?string $field,
        public ?string $proposedValue,
        public ?string $prescriptionId,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['submitted', 'done', 'rejected', 'closed']])]
        public string $status,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $submittedAt,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $resolvedAt,
        public ?string $resolutionMessage,
        public ?string $resultingPrescriptionId,
    ) {
    }

    public static function of(OwnerPortalRequest $request): self
    {
        return new self(
            $request->id,
            $request->kind,
            $request->petId,
            $request->field,
            $request->proposedValue,
            $request->prescriptionId,
            $request->status,
            ApiDate::formatLocal($request->submittedAt),
            null === $request->resolvedAt ? null : ApiDate::formatLocal($request->resolvedAt),
            $request->resolutionMessage,
            $request->resultingPrescriptionId,
        );
    }
}
