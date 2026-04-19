<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;

final readonly class VeterinaryCredentials
{
    public function __construct(
        private ProfessionalRegistrationNumber $registrationNumber,
        private ProfessionalTitle $professionalTitle,
        private ?SignatureImageKey $signatureImageKey,
    ) {
    }

    public function registrationNumber(): ProfessionalRegistrationNumber
    {
        return $this->registrationNumber;
    }

    public function professionalTitle(): ProfessionalTitle
    {
        return $this->professionalTitle;
    }

    public function signatureImageKey(): ?SignatureImageKey
    {
        return $this->signatureImageKey;
    }
}
