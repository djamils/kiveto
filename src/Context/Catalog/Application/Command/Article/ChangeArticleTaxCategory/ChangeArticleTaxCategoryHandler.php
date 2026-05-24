<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory;

use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeArticleTaxCategoryHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly TaxCategoryRegistryInterface $taxCategoryRegistry,
    ) {
    }

    public function __invoke(ChangeArticleTaxCategory $command): void
    {
        $taxCategory = TaxCategoryCode::fromString($command->taxCategoryCode);

        if (!$this->taxCategoryRegistry->has($taxCategory)) {
            throw new \DomainException(\sprintf('Unknown tax category code: "%s".', $command->taxCategoryCode));
        }

        $clinicId = ClinicId::fromString($command->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        $article->changeTaxCategory($taxCategory, $this->clock->now());

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
