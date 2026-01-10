<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\CreateClinic;

use App\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\LocaleCode;
use App\Clinic\Domain\ValueObject\TimeZone;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateClinicHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateClinic $command): string
    {
        $clinicId = ClinicId::fromString($this->uuidGenerator->generate());
        $slug     = ClinicSlug::fromString($command->slug);

        if ($this->clinicRepository->existsBySlug($slug)) {
            throw new DuplicateClinicSlugException($command->slug);
        }

        $now           = $this->clock->now();
        $clinicGroupId = $command->clinicGroupId ? ClinicGroupId::fromString($command->clinicGroupId) : null;

        $clinic = Clinic::create(
            $clinicId,
            $command->name,
            $slug,
            TimeZone::fromString($command->timeZone),
            LocaleCode::fromString($command->locale),
            $now,
            $clinicGroupId,
        );

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);

        return $clinicId->toString();
    }
}
