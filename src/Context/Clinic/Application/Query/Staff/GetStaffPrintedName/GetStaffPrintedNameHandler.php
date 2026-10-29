<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * D15: the one printed form of a staff name used by notifications. The
 * first and last names when the profile has them, else its display name.
 */
#[AsMessageHandler]
final readonly class GetStaffPrintedNameHandler
{
    private const array TITLES = ['DR' => 'Dr ', 'PR' => 'Pr '];

    public function __construct(
        private ClinicMembershipRepositoryInterface $memberships,
        private StaffProfileReadRepositoryInterface $profiles,
    ) {
    }

    public function __invoke(GetStaffPrintedName $query): ?string
    {
        $membership = $this->memberships->findByClinicAndUser(
            ClinicId::fromString($query->clinicId),
            UserId::fromString($query->userId),
        );

        $profile = null !== $membership ? $this->profiles->findByMembershipId($membership->id()->toString()) : null;

        if (null === $profile) {
            return null;
        }

        $name = mb_trim($profile->firstName . ' ' . $profile->lastName);

        return (self::TITLES[$profile->professionalTitle ?? ''] ?? '') . ('' !== $name ? $name : $profile->displayName);
    }
}
