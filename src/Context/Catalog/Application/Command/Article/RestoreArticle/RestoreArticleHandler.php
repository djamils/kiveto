<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\RestoreArticle;

use App\Context\Catalog\Application\Port\PharmaceuticalRefProviderInterface;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Exception\RegulatoryRestoreForbiddenException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestoreArticleHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly PharmaceuticalRefProviderInterface $pharmaceuticalRefProvider,
    ) {
    }

    public function __invoke(RestoreArticle $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        // Regulatory guard for DRUG articles with an authorizationRef
        if (ArticleKind::DRUG === $article->kind()) {
            $drugProps = $article->drugProperties();
            $authRef   = $drugProps?->authorizationRef();

            if (null !== $authRef) {
                $ref = $this->pharmaceuticalRefProvider->findById($authRef->toString());

                if (null === $ref || !$ref->isMarketable()) {
                    throw new RegulatoryRestoreForbiddenException($command->articleId, $authRef->toString());
                }
            }
        }

        $article->restore($this->clock->now());

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
