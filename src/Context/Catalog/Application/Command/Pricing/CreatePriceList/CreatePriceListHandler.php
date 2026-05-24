<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\CreatePriceList;

use App\Context\Catalog\Domain\Pricing\Exception\DefaultPriceListAlreadyExistsException;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreatePriceListHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CreatePriceList $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);

        if ($command->isDefault && $this->priceListRepository->hasDefaultForClinic($clinicId)) {
            throw new DefaultPriceListAlreadyExistsException($command->clinicId);
        }

        $priceListId = PriceListId::fromString($this->uuidGenerator->generate());
        $now         = $this->clock->now();

        $priceList = PriceList::create(
            id: $priceListId,
            clinicId: $clinicId,
            name: PriceListName::fromString($command->name),
            isDefault: $command->isDefault,
            createdAt: $now,
        );

        $this->priceListRepository->save($priceList);
        $this->domainEventPublisher->publish($priceList);

        return $priceListId->toString();
    }
}
