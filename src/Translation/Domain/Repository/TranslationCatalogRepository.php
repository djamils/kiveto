<?php

declare(strict_types=1);

namespace App\Translation\Domain\Repository;

use App\Translation\Domain\Model\TranslationCatalog;
use App\Translation\Domain\Model\ValueObject\TranslationCatalogId;

interface TranslationCatalogRepository
{
    public function save(TranslationCatalog $catalog): void;

    public function find(TranslationCatalogId $id): ?TranslationCatalog;
}
