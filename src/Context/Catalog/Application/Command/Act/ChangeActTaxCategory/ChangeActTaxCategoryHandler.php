<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeActTaxCategoryHandler
{
    public function __construct(
        private readonly ActRepositoryInterface $actRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly TaxCategoryRegistryInterface $taxCategoryRegistry,
    ) {
    }

    public function __invoke(ChangeActTaxCategory $command): void
    {
        $taxCategory = TaxCategoryCode::fromString($command->taxCategoryCode);

        if (!$this->taxCategoryRegistry->has($taxCategory)) {
            throw new \DomainException(\sprintf('Unknown tax category code: "%s".', $command->taxCategoryCode));
        }

        $clinicId = ClinicId::fromString($command->clinicId);
        $act      = $this->actRepository->findById(ActId::fromString($command->actId), $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($command->actId);
        }

        $act->changeTaxCategory($taxCategory, $this->clock->now());

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);
    }
}
