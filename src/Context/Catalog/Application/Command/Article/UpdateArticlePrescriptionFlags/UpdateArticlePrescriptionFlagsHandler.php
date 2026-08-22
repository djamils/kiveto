<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags;

use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateArticlePrescriptionFlagsHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(UpdateArticlePrescriptionFlags $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        $article->updatePrescriptionFlags(
            requiresPrescription: $command->requiresPrescription,
            prescriptionClass: null !== $command->prescriptionClass
                ? PrescriptionClass::from($command->prescriptionClass)
                : null,
            isControlledSubstance: $command->isControlledSubstance,
            updatedAt: $this->clock->now(),
        );

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
