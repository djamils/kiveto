<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Bus\QueryBusInterface;

/**
 * A query bus answering by query class: a fixed answer, or a closure given the
 * query (which may throw, like a handler refusing an unknown id). An unexpected
 * query answers null. Every query asked is kept.
 */
final class AnsweringQueryBus implements QueryBusInterface
{
    /** @var list<object> */
    public array $asked = [];

    /**
     * @param array<class-string, mixed> $answers
     */
    public function __construct(private array $answers = [])
    {
    }

    /**
     * @param class-string $queryClass
     */
    public function answer(string $queryClass, mixed $answer): self
    {
        $this->answers[$queryClass] = $answer;

        return $this;
    }

    public function ask(object $query, object ...$stamps): mixed
    {
        $this->asked[] = $query;
        $answer        = $this->answers[$query::class] ?? null;

        return $answer instanceof \Closure ? $answer($query) : $answer;
    }
}
