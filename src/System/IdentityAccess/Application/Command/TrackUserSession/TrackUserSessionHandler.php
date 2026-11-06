<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\TrackUserSession;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TrackUserSessionHandler
{
    private const int TOUCH_SECONDS = 60;

    public function __construct(
        private UserSessionStoreInterface $sessions,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TrackUserSession $command): bool
    {
        $hash    = hash('sha256', $command->sessionId);
        $now     = $this->clock->now();
        $session = $this->sessions->find($hash);

        if (null === $session) {
            $this->sessions->open($this->uuids->generate(), $command->userId, $hash, self::cut($command->userAgent, 255), $command->ipAddress, $now);

            return true;
        }

        // A session id carried by another account is not this one's to keep.
        if ($session->revoked || $session->userId !== $command->userId) {
            return false;
        }

        if ($now->getTimestamp() - $session->lastSeenAt->getTimestamp() >= self::TOUCH_SECONDS) {
            $this->sessions->touch($hash, $command->ipAddress, $now);
        }

        return true;
    }

    private static function cut(?string $text, int $length): ?string
    {
        return null !== $text ? mb_substr($text, 0, $length) : null;
    }
}
