<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicAccount;

final readonly class ClinicAccountView
{
    public function __construct(
        public string $clinicId,
        public string $plan,
        public string $planLabel,
        public ?\DateTimeImmutable $pilotEndsOn,
        public ?string $parallelSoftware,
        public ?string $parallelSoftwareLabel,
        public string $internalNote,
        public ?\DateTimeImmutable $onboardedAt,
        public ?string $suspensionReason,
        public ?string $suspensionReasonLabel,
        public ?string $suspensionComment,
        public ?\DateTimeImmutable $suspendedAt,
        public ?string $suspendedByName,
    ) {
    }
}
