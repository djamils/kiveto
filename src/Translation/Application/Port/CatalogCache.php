<?php

declare(strict_types=1);

namespace App\Translation\Application\Port;

use App\Translation\Domain\Model\ValueObject\TranslationCatalogId;

interface CatalogCache
{
    /**
     * @return array<string, string>|null
     */
    public function get(TranslationCatalogId $id): ?array;

    /**
     * @param array<string, string> $catalog
     */
    public function save(TranslationCatalogId $id, array $catalog, int $ttl): void;

    public function delete(TranslationCatalogId $id): void;
}
