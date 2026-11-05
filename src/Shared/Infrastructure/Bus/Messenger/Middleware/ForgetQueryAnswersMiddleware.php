<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Infrastructure\Bus\Messenger\QueryAnswerCache;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * On the command buses: no query answer is kept while a command runs, and
 * none survives it, failed or not.
 */
final readonly class ForgetQueryAnswersMiddleware implements MiddlewareInterface
{
    public function __construct(private QueryAnswerCache $answers)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->answers->commandStarted();

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->answers->commandFinished();
        }
    }
}
