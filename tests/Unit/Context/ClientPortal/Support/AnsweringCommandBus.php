<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Shared\Application\Bus\CommandBusInterface;

/** Keeps every command and answers it with the closure. */
final class AnsweringCommandBus implements CommandBusInterface
{
    /** @var list<object> */
    public array $commands = [];

    /** @param \Closure(object): mixed $answer */
    public function __construct(private readonly \Closure $answer)
    {
    }

    public function dispatch(object $command, object ...$stamps): mixed
    {
        $this->commands[] = $command;

        return ($this->answer)($command);
    }
}
