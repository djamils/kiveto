<?php

declare(strict_types=1);

namespace App\Translation\Domain\Repository;

use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\TranslationCatalogId;

interface TranslationCatalogRepository
{
    public function save(TranslationCatalog $catalog): void;

    public function find(TranslationCatalogId $id): ?TranslationCatalog;
}
