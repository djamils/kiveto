<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * A console sign-in between the password and the second factor, kept in
 * the session for five minutes and five codes. It authenticates nothing.
 */
final readonly class PendingSignIn
{
    public const string SESSION_KEY = 'console.pending_sign_in';

    public const int TTL_SECONDS = 300;

    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        public string $userId,
        public string $email,
        public int $startedAt,
        public int $attempts = 0,
    ) {
    }

    public static function from(SessionInterface $session, int $now): ?self
    {
        $pending = $session->get(self::SESSION_KEY);

        if (!\is_array($pending)) {
            return null;
        }

        $userId    = $pending['user_id'] ?? null;
        $email     = $pending['email'] ?? null;
        $startedAt = $pending['started_at'] ?? null;
        $attempts  = $pending['attempts'] ?? null;
        $complete  = \is_string($userId) && \is_string($email) && \is_int($startedAt) && \is_int($attempts);

        if (!$complete || $now - $startedAt > self::TTL_SECONDS) {
            return null;
        }

        return new self($userId, $email, $startedAt, $attempts);
    }

    public function store(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY, [
            'user_id'    => $this->userId,
            'email'      => $this->email,
            'started_at' => $this->startedAt,
            'attempts'   => $this->attempts,
        ]);
    }

    /**
     * One more wrong code; null once the attempts are spent (the pending
     * sign-in is dropped).
     */
    public function failed(SessionInterface $session): ?self
    {
        $next = new self($this->userId, $this->email, $this->startedAt, $this->attempts + 1);

        if ($next->attempts >= self::MAX_ATTEMPTS) {
            self::clear($session);

            return null;
        }

        $next->store($session);

        return $next;
    }

    public static function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }
}
