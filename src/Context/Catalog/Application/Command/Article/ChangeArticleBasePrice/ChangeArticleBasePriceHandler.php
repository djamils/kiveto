<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeArticleBasePriceHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClinicInfoProviderInterface $clinicInfoProvider,
    ) {
    }

    public function __invoke(ChangeArticleBasePrice $command): void
    {
        $clinicId       = ClinicId::fromString($command->clinicId);
        $clinicCurrency = $this->clinicInfoProvider->getCurrencyCode($clinicId);

        if ($clinicCurrency->toString() !== $command->basePriceCurrency) {
            throw new ClinicCurrencyMismatchException($clinicCurrency->toString(), $command->basePriceCurrency);
        }

        $article = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        $article->changeBasePrice(
            CatalogPrice::fromPrimitives(
                $command->basePriceMinorUnits,
                $command->basePriceCurrency,
                $command->basePriceMode,
            ),
            $this->clock->now(),
        );

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
