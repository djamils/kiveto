<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\OnboardStaffMember;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Shared\Application\Bus\CommandInterface;

final readonly class OnboardStaffMember implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $userId,
        public ClinicMemberRole $role,
        public ClinicMembershipEngagement $engagement,
        public string $firstName,
        public string $lastName,
        public string $displayName,
        public string $agendaColor,
        public int $sortOrder,
        public bool $isVisibleInAgenda,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validUntil = null,
        public ?string $phone = null,
        public ?string $registrationNumber = null,
        public ?ProfessionalTitle $professionalTitle = null,
        public ?string $signatureImageKey = null,
    ) {
    }
}
