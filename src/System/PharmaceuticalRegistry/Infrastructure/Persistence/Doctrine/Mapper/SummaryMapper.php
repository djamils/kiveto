<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\Entity\Summary;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SummaryLink;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SummarySection;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SummaryEntity;
use Symfony\Component\Uid\Uuid;

final readonly class SummaryMapper
{
    public function toDomain(SummaryEntity $entity): Summary
    {
        $sections = array_map(
            static fn (array $row) => new SummarySection(
                titleCode: $row['titleCode'],
                titleLabel: $row['titleLabel'],
                content: $row['content'],
            ),
            $entity->getSections(),
        );

        $sourceUrl = null !== $entity->getSourceUrl() ? SummaryLink::fromString($entity->getSourceUrl()) : null;

        return Summary::of($sections, $sourceUrl, $entity->getLastUpdatedAt());
    }

    public function toEntity(Summary $summary, AuthorizationEntity $authorization): SummaryEntity
    {
        $entity = new SummaryEntity();
        $entity->setId(Uuid::v7());
        $entity->setAuthorization($authorization);

        $sections = array_map(
            static fn (SummarySection $section) => [
                'titleCode'  => $section->titleCode(),
                'titleLabel' => $section->titleLabel(),
                'content'    => $section->content(),
            ],
            $summary->sections(),
        );

        $entity->setSections(array_values($sections));
        $entity->setSourceUrl($summary->sourceUrl()?->toString());
        $entity->setLastUpdatedAt($summary->lastUpdatedAt());

        return $entity;
    }
}
