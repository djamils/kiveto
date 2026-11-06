<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

/**
 * The author of an entry, with the name it had at that moment: the log is
 * read as it was written, even after a rename or the account's deletion.
 */
final class AuditActor
{
    private const int MAX_NAME_LENGTH = 160;

    private ActorKind $kind;
    private ?string $id;
    private ?string $name;

    private function __construct()
    {
    }

    public static function kiveto(string $id, string $name): self
    {
        return self::identified(ActorKind::KIVETO, $id, $name);
    }

    public static function staff(string $id, string $name): self
    {
        return self::identified(ActorKind::STAFF, $id, $name);
    }

    /**
     * A job of the platform, named after what it does (« Import ANMV »).
     */
    public static function system(string $name): self
    {
        $actor       = new self();
        $actor->kind = ActorKind::SYSTEM;
        $actor->id   = null;
        $actor->name = self::normalisedName($name);

        return $actor;
    }

    public static function unknown(): self
    {
        $actor       = new self();
        $actor->kind = ActorKind::UNKNOWN;
        $actor->id   = null;
        $actor->name = null;

        return $actor;
    }

    public function kind(): ActorKind
    {
        return $this->kind;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->id === $other->id && $this->name === $other->name;
    }

    private static function identified(ActorKind $kind, string $id, string $name): self
    {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('An identified actor needs an id.');
        }

        $actor       = new self();
        $actor->kind = $kind;
        $actor->id   = $id;
        $actor->name = self::normalisedName($name);

        return $actor;
    }

    private static function normalisedName(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            throw new \InvalidArgumentException('An actor needs a name.');
        }

        return mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }
}
