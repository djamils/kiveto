<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Bus\RequestCacheableQueryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * A request-cacheable query asked twice in a request, with the same values
 * and no stamp, is handled once (QueryAnswerCache).
 */
class QueryBus implements QueryBusInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ?QueryAnswerCache $answers = null,
    ) {
        $this->messageBus = $messageBus;
    }

    public function ask(object $query, object ...$stamps): mixed
    {
        if (null !== $this->answers && [] === $stamps && $query instanceof RequestCacheableQueryInterface) {
            return $this->answers->answer($query::class . "\0" . serialize($query), fn (): mixed => $this->dispatch($query, []));
        }

        return $this->dispatch($query, $stamps);
    }

    /**
     * @param array<object> $stamps
     */
    private function dispatch(object $query, array $stamps): mixed
    {
        try {
            /** @var array<StampInterface> $stampsArray */
            $stampsArray = $stamps;
            $envelope    = Envelope::wrap($query, $stampsArray);

            return $this->handle($envelope);
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
