<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\RegisterTaxCategory;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\TaxCategory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterTaxCategoryHandler
{
    public function __construct(
        private TaxCategoryRepositoryInterface $categoryRepo,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterTaxCategory $command): void
    {
        $existing = $this->categoryRepo->findByCode($command->code);

        if (null === $existing) {
            $category = TaxCategory::create($command->code, $command->displayName, $command->description, $this->clock);
            $this->categoryRepo->save($category);
            $this->domainEventPublisher->publish($category);

            return;
        }

        $existing->updateDisplay($command->displayName, $command->description, $this->clock);
        $this->categoryRepo->save($existing);
    }
}
