<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipWorkTime\ChangeClinicMembershipWorkTime;
use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\MembershipDetails;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Story;

/**
 * The team page in every state it shows (equipe-v-topbar_5): the Paris team
 * at a realistic size — partners and employees, part-time, a contractor whose
 * access ends, a replacement arriving next week, a newcomer who has not signed
 * in yet, an intern whose internship is over, an assistant who left — Lyon a
 * short team (with a member added from the back office, so without a
 * profile), and the calm clinic a team of one.
 *
 * Loaded last, so that no story above ever picks one of these members for its
 * visits or its stock movements. Every sign-in is stamped too: the team page
 * reads « en ligne », « hier, 18:52 » or « jamais connecté » from it.
 */
final class TeamDemoStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly UserRepositoryInterface $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        $paris = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        // A replacement veterinarian, booked for a week starting next Monday.
        $nextMonday = $today->modify('monday next week');
        $this->onboard(
            clinicId: $paris,
            email: 'thomas.nguyen@kiveto.local',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::REPLACEMENT,
            firstName: 'Thomas',
            lastName: 'Nguyen',
            color: '#8d7bd6',
            validFrom: $nextMonday,
            validUntil: $nextMonday->modify('+4 days 23:59:59'),
            registrationNumber: '40318',
        );

        // Hired this week, account ready, not signed in yet.
        $this->onboard(
            clinicId: $paris,
            email: 'camille.roux@kiveto.local',
            role: ClinicMemberRole::RECEPTIONIST,
            engagement: ClinicMembershipEngagement::FIXED_TERM,
            firstName: 'Camille',
            lastName: 'Roux',
            color: '#d65d7a',
            validFrom: $today->modify('-2 days'),
            validUntil: $today->modify('+5 months 23:59:59'),
        );

        // An intern whose internship ended a fortnight ago: the window closed by itself.
        $this->onboard(
            clinicId: $paris,
            email: 'lea.durand@kiveto.local',
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::INTERN,
            firstName: 'Léa',
            lastName: 'Durand',
            color: '#7a9e3b',
            validFrom: $today->modify('-4 months'),
            validUntil: $today->modify('-15 days 23:59:59'),
        );

        // An assistant who left: switched off, everything they did kept.
        $kevin = $this->onboard(
            clinicId: $paris,
            email: 'kevin.garcia@kiveto.local',
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Kévin',
            lastName: 'Garcia',
            color: '#64748b',
            validFrom: $today->modify('-3 years'),
        );
        $this->commandBus->dispatch(new DisableClinicMembership($this->membershipId($kevin, $paris)));

        // The reception works four days a week.
        $reception = $this->userId('accueil@kiveto.local');
        $this->commandBus->dispatch(new ChangeClinicMembershipWorkTime($this->membershipId($reception, $paris), 80));

        // Who has signed in, and when (UTC, as the login listener writes it).
        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $signIns = [
            'vet@kiveto.local'            => $now->modify('-25 minutes'),
            'accueil@kiveto.local'        => $now->modify('-2 hours'),
            'assistant@kiveto.local'      => $now->modify('-3 hours'),
            'manager@kiveto.local'        => $now->modify('-1 day')->setTime(16, 52),
            'contractor@kiveto.local'     => $now->modify('-2 days')->setTime(9, 14),
            'vet2@kiveto.local'           => $now->modify('-12 days')->setTime(17, 3),
            'vet.lyon@kiveto.local'       => $now->modify('-1 day')->setTime(7, 40),
            'assistant.lyon@kiveto.local' => $now->modify('-3 days')->setTime(12, 5),
            'lea.durand@kiveto.local'     => $now->modify('-16 days')->setTime(15, 30),
            'kevin.garcia@kiveto.local'   => $now->modify('-4 months')->setTime(16, 0),
        ];

        foreach ($signIns as $email => $at) {
            $this->users->recordLogin(UserId::fromString($this->userId($email)), $at);
        }

        // recordLogin writes past the unit of work.
        $this->entityManager->clear();
    }

    private function onboard(
        string $clinicId,
        string $email,
        ClinicMemberRole $role,
        ClinicMembershipEngagement $engagement,
        string $firstName,
        string $lastName,
        string $color,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil = null,
        ?string $registrationNumber = null,
    ): string {
        $user = ClinicUserFactory::new()->withEmail($email)->withPlainPassword('kiveto2026')->create();
        $id   = $user->getId()->toRfc4122();

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: $clinicId,
            userId: $id,
            role: $role,
            engagement: $engagement,
            firstName: $firstName,
            lastName: $lastName,
            displayName: ClinicMemberRole::VETERINARY === $role ? $lastName : $firstName . ' ' . $lastName,
            agendaColor: $color,
            sortOrder: 10,
            // Not in the agenda: none of them sees patients today.
            isVisibleInAgenda: false,
            validFrom: $validFrom->setTimezone(new \DateTimeZone('UTC')),
            validUntil: $validUntil?->setTimezone(new \DateTimeZone('UTC')),
            registrationNumber: $registrationNumber,
            professionalTitle: null !== $registrationNumber ? ProfessionalTitle::DR : null,
        ));

        return $id;
    }

    private function userId(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s not found: the clinic staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
    }

    private function membershipId(string $userId, string $clinicId): string
    {
        $membership = $this->queryBus->ask(new GetUserMembershipInClinic($userId, $clinicId));
        \assert($membership instanceof MembershipDetails);

        return $membership->membershipId;
    }
}
