<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The answers of the request-cacheable queries (RequestCacheableQueryInterface)
 * asked during the request. While a command runs nothing is kept nor served,
 * so that a handler always reads what it has just written; once the last
 * command is over everything is forgotten. The kernel reset empties it
 * between requests and worker messages.
 */
final class QueryAnswerCache implements ResetInterface
{
    /** @var array<string, mixed> by query */
    private array $answers = [];

    /** Commands running, nested ones included. */
    private int $commands = 0;

    /**
     * The kept answer of the query, else the one `$ask` gives, kept unless a
     * command is running.
     *
     * @param \Closure(): mixed $ask
     */
    public function answer(string $query, \Closure $ask): mixed
    {
        if ($this->commands > 0) {
            return $ask();
        }

        if (!\array_key_exists($query, $this->answers)) {
            $this->answers[$query] = $ask();
        }

        return $this->answers[$query];
    }

    /**
     * The answer kept for the query, without asking: null when none is kept,
     * and always while a command runs.
     */
    public function kept(string $query): mixed
    {
        return $this->commands > 0 ? null : ($this->answers[$query] ?? null);
    }

    public function commandStarted(): void
    {
        ++$this->commands;
        $this->answers = [];
    }

    public function commandFinished(): void
    {
        $this->commands = max(0, $this->commands - 1);
        $this->answers  = [];
    }

    public function reset(): void
    {
        $this->answers  = [];
        $this->commands = 0;
    }
}
