<?php

declare(strict_types=1);

namespace App\Translation\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Translation\Domain\Event\TranslationDeleted;
use App\Translation\Domain\Event\TranslationUpserted;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;

/**
 * Aggregate root representing a catalog (scope + locale + domain).
 */
final class TranslationCatalog extends AggregateRoot
{
    /** @var array<string, TranslationEntry> */
    private array $entries = [];

    /** @var array<string, TranslationKey> */
    private array $removedKeys = [];

    private function __construct(private TranslationCatalogId $id)
    {
    }

    /**
     * @param list<TranslationEntry> $entries
     */
    public static function reconstitute(TranslationCatalogId $id, array $entries): self
    {
        $catalog = new self($id);

        foreach ($entries as $entry) {
            $catalog->entries[$entry->key()->toString()] = $entry;
        }

        return $catalog;
    }

    public static function createEmpty(TranslationCatalogId $id): self
    {
        return new self($id);
    }

    public function id(): TranslationCatalogId
    {
        return $this->id;
    }

    public function upsert(
        TranslationKey $key,
        TranslationText $text,
        \DateTimeImmutable $now,
        ?ActorId $actorId = null,
        ?string $description = null,
    ): void {
        $entry = $this->entries[$key->toString()] ?? null;

        if (null !== $entry) {
            $this->entries[$key->toString()] = $entry->replaceText($text, $now, $actorId, $description);
        } else {
            $this->entries[$key->toString()] = new TranslationEntry($key, $text, $now, $now, $actorId, $actorId, $description);
        }

        unset($this->removedKeys[$key->toString()]);

        $this->recordDomainEvent(
            new TranslationUpserted(
                $this->id->scope()->value,
                $this->id->locale()->toString(),
                $this->id->domain()->toString(),
                $key->toString(),
                $actorId?->toString(),
            ),
        );
    }

    public function remove(TranslationKey $key, ?ActorId $actorId, \DateTimeImmutable $removedAt): void
    {
        if (!isset($this->entries[$key->toString()])) {
            return;
        }

        unset($this->entries[$key->toString()]);
        $this->removedKeys[$key->toString()] = $key;

        $this->recordDomainEvent(
            new TranslationDeleted(
                $this->id->scope()->value,
                $this->id->locale()->toString(),
                $this->id->domain()->toString(),
                $key->toString(),
                $actorId?->toString(),
                $removedAt,
            ),
        );
    }

    /**
     * @return list<TranslationEntry>
     */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    /**
     * @return list<TranslationKey>
     */
    public function removedKeys(): array
    {
        return array_values($this->removedKeys);
    }

    /**
     * @return array<string, string>
     */
    public function toKeyValue(): array
    {
        $map = [];

        foreach ($this->entries as $entry) {
            $map[$entry->key()->toString()] = $entry->text()->toString();
        }

        return $map;
    }

    public function hasKey(TranslationKey $key): bool
    {
        return isset($this->entries[$key->toString()]);
    }
}
