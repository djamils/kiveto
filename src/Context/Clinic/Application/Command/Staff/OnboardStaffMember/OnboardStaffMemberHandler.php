<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\OnboardStaffMember;

use App\Context\Clinic\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OnboardStaffMemberHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private StaffProfileRepositoryInterface $profileRepository,
        private UserExistenceCheckerInterface $userExistenceChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(OnboardStaffMember $command): string
    {
        if (!\in_array($command->role, [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT], true)) {
            throw new \InvalidArgumentException(\sprintf(
                'OnboardStaffMember only supports VETERINARY and VETERINARY_ASSISTANT roles. Got "%s".',
                $command->role->value,
            ));
        }

        $clinicId = ClinicId::fromString($command->clinicId);

        $clinic = $this->clinicRepository->findById($clinicId);
        if (null === $clinic) {
            throw new \InvalidArgumentException(\sprintf('Clinic with ID "%s" does not exist.', $command->clinicId));
        }

        if (!$this->userExistenceChecker->exists($command->userId)) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" does not exist.', $command->userId));
        }

        $userId = UserId::fromString($command->userId);

        if ($this->membershipRepository->existsByClinicAndUser($clinicId, $userId)) {
            throw new ClinicMembershipAlreadyExistsException(\sprintf(
                'User "%s" already has a membership in clinic "%s".',
                $command->userId,
                $command->clinicId,
            ));
        }

        $now          = $this->clock->now();
        $membershipId = ClinicMembershipId::fromString($this->uuidGenerator->generate());
        $profileId    = StaffProfileId::fromString($this->uuidGenerator->generate());
        $validFrom    = $command->validFrom ?? $now;

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: $clinicId,
            userId: $userId,
            role: $command->role,
            engagement: $command->engagement,
            validFrom: $validFrom,
            validUntil: $command->validUntil,
            createdAt: $now,
        );

        $this->membershipRepository->save($membership);

        $phoneNumber = null !== $command->phone ? PhoneNumber::fromString($command->phone) : null;

        $profile = StaffProfile::create(
            id: $profileId,
            membershipId: $membershipId,
            firstName: $command->firstName,
            lastName: $command->lastName,
            displayName: DisplayName::fromString($command->displayName),
            phoneNumber: $phoneNumber,
            agendaColor: HexColor::fromString($command->agendaColor),
            sortOrder: $command->sortOrder,
            isVisibleInAgenda: $command->isVisibleInAgenda,
            createdAt: $now,
            updatedAt: $now,
        );

        $hasCredentials = null !== $command->registrationNumber
            && null !== $command->professionalTitle
            && $command->role->canHoldVeterinaryCredentials();

        if ($hasCredentials) {
            \assert(null !== $command->registrationNumber);
            \assert(null !== $command->professionalTitle);

            $signatureImageKey = null !== $command->signatureImageKey
                ? SignatureImageKey::fromString($command->signatureImageKey)
                : null;

            $credentials = new VeterinaryCredentials(
                registrationNumber: ProfessionalRegistrationNumber::fromString($command->registrationNumber),
                professionalTitle: $command->professionalTitle,
                signatureImageKey: $signatureImageKey,
            );

            $profile->registerVeterinaryCredentials($credentials, $now);
        }

        $this->profileRepository->save($profile);

        $this->domainEventPublisher->publish($membership);

        return $profileId->toString();
    }
}
