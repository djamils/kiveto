<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SyncPlatformFlows;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Command\AdvanceSyncCursor\AdvanceSyncCursor;
use App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus\RecordLifecycleStatus;
use App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow\RecordUnprocessedFlow;
use App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow\RecordUnprocessedFlowHandler;
use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;
use App\System\ElectronicInvoicing\Application\Policy\ResolveTransmissionForFlow;
use App\System\ElectronicInvoicing\Application\Port\LifecycleMessageParserInterface;
use App\System\ElectronicInvoicing\Application\Port\UnprocessedFlowsInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads an organisation's new flows from its platform — network only — and
 * records what they say through commands.
 *
 * Flows left unprocessed by earlier runs are retried first. New flows are
 * read in (updatedAt, flowId) order, page after page (capped per run), and
 * the cursor is advanced at the end to the last flow processed: a run cut
 * short resumes exactly after it, flows sharing a timestamp included. A flow
 * that cannot be parsed or that names an unknown document is kept as
 * unprocessed rather than lost.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class SyncPlatformFlowsHandler
{
    public const int PAGE_SIZE = 50;
    public const int MAX_PAGES = 10;

    public function __construct(
        private PlatformConnectionRepositoryInterface $connections,
        private FlowSearcherInterface $searcher,
        private FlowFetcherInterface $fetcher,
        private LifecycleMessageParserInterface $parser,
        private ResolveTransmissionForFlow $resolver,
        private UnprocessedFlowsInterface $unprocessed,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(SyncPlatformFlows $command): void
    {
        $organisation = $command->organisationId;
        $connection   = $this->connections->byOrganisation(Siren::fromInput($organisation));

        if (null === $connection || !$connection->isPolled()) {
            return;
        }

        foreach ($this->unprocessed->toRetry($organisation, RecordUnprocessedFlowHandler::MAX_ATTEMPTS) as $flowId) {
            if ($this->process($organisation, $flowId)) {
                $this->commandBus->dispatch(new RecordUnprocessedFlow($organisation, $flowId, null));
            }
        }

        $after = $connection->cursor()->updatedAfter();
        $last  = $connection->cursor()->lastFlowId();
        $moved = false;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $flows = $this->searcher->search($organisation, $after, $last, self::PAGE_SIZE);

            foreach ($flows->flows as $flow) {
                $this->process($organisation, $flow->flowId);
                $after = $flow->updatedAt;
                $last  = $flow->flowId;
                $moved = true;
            }

            if (!$flows->hasMore) {
                break;
            }
        }

        if ($moved) {
            $this->commandBus->dispatch(new AdvanceSyncCursor($organisation, $after->format('Y-m-d\TH:i:s.uP'), $last));
        }
    }

    /**
     * @return bool whether every message of the flow was recorded
     */
    private function process(string $organisation, string $flowId): bool
    {
        $content = $this->fetcher->fetch($organisation, $flowId)->content;

        try {
            $messages = $this->parser->parse($content);
        } catch (UnparsableLifecycleMessageException) {
            $this->commandBus->dispatch(new RecordUnprocessedFlow($organisation, $flowId, RecordUnprocessedFlow::UNPARSABLE));

            return false;
        }

        $complete = true;

        foreach ($messages as $message) {
            $transmission = $this->resolver->resolve($organisation, $message);
            $code         = LifecycleStatusCode::tryFrom($message->statusCode);

            if (null === $transmission || null === $code) {
                $complete = false;

                continue;
            }

            $this->commandBus->dispatch(new RecordLifecycleStatus(
                transmissionId: $transmission->id()->toString(),
                statusCode: $code->value,
                reasonCode: $message->reasonCode,
                reasonText: $message->reasonText,
                sourceFlowId: $flowId,
                at: $message->issuedAt->format(\DateTimeInterface::ATOM),
            ));
        }

        if (!$complete) {
            $this->commandBus->dispatch(new RecordUnprocessedFlow($organisation, $flowId, RecordUnprocessedFlow::UNRESOLVED));
        }

        return $complete;
    }
}
