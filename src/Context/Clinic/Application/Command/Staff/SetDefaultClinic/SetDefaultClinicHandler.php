<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\SetDefaultClinic;

use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetDefaultClinicHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(SetDefaultClinic $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $userId   = UserId::fromString($command->userId);

        $target = $this->membershipRepository->findByClinicAndUser($clinicId, $userId);

        if (null === $target) {
            throw new \InvalidArgumentException(\sprintf(
                'No membership found for clinic "%s" and user "%s".',
                $command->clinicId,
                $command->userId,
            ));
        }

        $currentDefault = $this->membershipRepository->findDefaultForUser($userId);

        if (null !== $currentDefault && $currentDefault->id()->toString() !== $target->id()->toString()) {
            $currentDefault->clearDefault();
            $target->setAsDefault();

            $this->membershipRepository->saveAll($currentDefault, $target);

            $this->domainEventPublisher->publish($currentDefault);
            $this->domainEventPublisher->publish($target);

            return;
        }

        $target->setAsDefault();

        $this->membershipRepository->saveAll($target);

        $this->domainEventPublisher->publish($target);
    }
}
