<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Shared\Application\Bus\CommandInterface;

final readonly class RegisterVeterinaryCredentials implements CommandInterface
{
    public function __construct(
        public string $profileId,
        public string $registrationNumber,
        public ProfessionalTitle $professionalTitle,
        public ?string $signatureImageKey = null,
    ) {
    }
}
