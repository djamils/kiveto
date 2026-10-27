<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Inbox;

/**
 * One bell entry for one user in one clinic: what happened (title, detail),
 * where clicking takes the user (route + parameters), who caused it
 * (initials, optional), and whether it was read. Entries are posted under an
 * idempotency key, at most once per user.
 */
final class InAppNotification
{
    public const int TITLE_MAX  = 255;
    public const int DETAIL_MAX = 500;
    public const int ROUTE_MAX  = 120;
    public const int KEY_MAX    = 190;

    /** The route parameters, JSON-encoded, at most. */
    public const int ROUTE_PARAMS_MAX = 1000;

    /** Entries older than this are purged and never listed. */
    public const int RETENTION_DAYS = 30;

    /**
     * @param array<string, string> $routeParams
     */
    private function __construct(
        private readonly InAppNotificationId $id,
        private readonly string $clinicId,
        private readonly string $userId,
        private readonly InAppKind $kind,
        private readonly string $title,
        private readonly string $detail,
        private readonly string $route,
        private readonly array $routeParams,
        private readonly ?string $actorInitials,
        private readonly string $idempotencyKey,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $readAt,
    ) {
    }

    /**
     * @param array<string, string> $routeParams
     */
    public static function post(
        InAppNotificationId $id,
        string $clinicId,
        string $userId,
        InAppKind $kind,
        string $title,
        string $detail,
        string $route,
        array $routeParams,
        ?string $actorInitials,
        string $idempotencyKey,
        \DateTimeImmutable $now,
    ): self {
        $title          = mb_trim($title);
        $route          = mb_trim($route);
        $idempotencyKey = mb_trim($idempotencyKey);
        $actorInitials  = null !== $actorInitials && '' !== mb_trim($actorInitials) ? mb_substr(mb_trim($actorInitials), 0, 4) : null;

        if ('' === $title || '' === $route || '' === $idempotencyKey || mb_strlen($idempotencyKey) > self::KEY_MAX || mb_strlen($route) > self::ROUTE_MAX) {
            throw new \InvalidArgumentException(\sprintf('A bell entry needs a title, a route (%d characters at most) and an idempotency key (%d at most).', self::ROUTE_MAX, self::KEY_MAX));
        }

        if (\in_array('', array_map(strval(...), array_keys($routeParams)), true) || mb_strlen(json_encode($routeParams, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)) > self::ROUTE_PARAMS_MAX) {
            throw new \InvalidArgumentException(\sprintf('The route parameters of a bell entry need names and take %d characters at most.', self::ROUTE_PARAMS_MAX));
        }

        return new self($id, $clinicId, $userId, $kind, mb_substr($title, 0, self::TITLE_MAX), mb_substr(mb_trim($detail), 0, self::DETAIL_MAX), $route, $routeParams, $actorInitials, $idempotencyKey, $now, null);
    }

    /**
     * @param array<string, string> $routeParams
     */
    public static function reconstitute(
        InAppNotificationId $id,
        string $clinicId,
        string $userId,
        InAppKind $kind,
        string $title,
        string $detail,
        string $route,
        array $routeParams,
        ?string $actorInitials,
        string $idempotencyKey,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $readAt,
    ): self {
        return new self($id, $clinicId, $userId, $kind, $title, $detail, $route, $routeParams, $actorInitials, $idempotencyKey, $createdAt, $readAt);
    }

    public function id(): InAppNotificationId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function kind(): InAppKind
    {
        return $this->kind;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function route(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, string>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function actorInitials(): ?string
    {
        return $this->actorInitials;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }
}
