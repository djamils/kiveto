<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\CreateClinic;

use App\Context\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateClinicHandler
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
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
            id: $clinicId,
            name: $command->name,
            slug: $slug,
            timeZone: TimeZone::fromString($command->timeZone),
            locale: Locale::fromString($command->locale),
            countryCode: CountryCode::fromString($command->countryCode),
            currencyCode: CurrencyCode::fromString($command->currencyCode),
            createdAt: $now,
            clinicGroupId: $clinicGroupId,
            jurisdictionCode: $command->jurisdictionCode,
        );

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);

        return $clinicId->toString();
    }
}
