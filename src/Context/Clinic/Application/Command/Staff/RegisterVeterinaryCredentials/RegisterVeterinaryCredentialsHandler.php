<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials;

use App\Context\Clinic\Application\Exception\CannotRegisterCredentialsForNonVeterinarianRole;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterVeterinaryCredentialsHandler
{
    public function __construct(
        private StaffProfileRepositoryInterface $profileRepository,
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterVeterinaryCredentials $command): void
    {
        $profileId = StaffProfileId::fromString($command->profileId);

        $profile = $this->profileRepository->findById($profileId);
        if (null === $profile) {
            throw new \InvalidArgumentException(\sprintf('StaffProfile with ID "%s" not found.', $command->profileId));
        }

        $membership = $this->membershipRepository->findById($profile->membershipId());
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('ClinicMembership "%s" not found.', $profile->membershipId()->toString()));
        }

        if (!$membership->role()->canHoldVeterinaryCredentials()) {
            throw new CannotRegisterCredentialsForNonVeterinarianRole(
                $membership->role()->value,
                $profile->membershipId()->toString(),
            );
        }

        $signatureImageKey = null !== $command->signatureImageKey
            ? SignatureImageKey::fromString($command->signatureImageKey)
            : null;

        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString($command->registrationNumber),
            professionalTitle: $command->professionalTitle,
            signatureImageKey: $signatureImageKey,
        );

        $profile->registerVeterinaryCredentials($credentials, $this->clock->now());

        $this->profileRepository->save($profile);
    }
}
