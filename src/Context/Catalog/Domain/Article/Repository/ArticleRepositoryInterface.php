<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Repository;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface ArticleRepositoryInterface
{
    public function save(Article $article): void;

    public function findById(ArticleId $id, ClinicId $clinicId): ?Article;

    public function findByCode(ArticleCode $code, ClinicId $clinicId): ?Article;

    public function findByGtin(Gtin $gtin, ClinicId $clinicId): ?Article;

    public function existsByCode(ArticleCode $code, ClinicId $clinicId): bool;

    public function existsByGtin(Gtin $gtin, ClinicId $clinicId): bool;
}
