<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Application\Document\DocumentClinic;

final readonly class ClinicDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timeZone,
        public string $locale,
        public string $countryCode,
        public ?string $jurisdictionCode,
        public string $currencyCode,
        public ClinicStatus $status,
        public ?string $clinicGroupId,
        public string $createdAt,
        public string $updatedAt,
        // Optional so the collection read, which has no aggregate at hand, can
        // skip it: only the document routes need the identity block.
        public ?LetterheadDto $letterhead = null,
        public ?string $brandColor = null,
        public ?string $logoKey = null,
    ) {
    }

    /**
     * The clinic as the shared document templates print it.
     */
    public function toDocumentClinic(): DocumentClinic
    {
        return new DocumentClinic(
            name: $this->name,
            letterhead: $this->letterhead?->toDocumentLetterhead(),
            brandColor: $this->brandColor,
            logoKey: $this->logoKey,
        );
    }
}
