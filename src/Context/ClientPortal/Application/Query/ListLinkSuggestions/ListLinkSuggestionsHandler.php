<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListLinkSuggestions;

use App\Context\ClientPortal\Application\Service\LinkSuggestionCandidate;
use App\Context\ClientPortal\Application\Service\LinkSuggestions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListLinkSuggestionsHandler
{
    public function __construct(private LinkSuggestions $suggestions)
    {
    }

    /**
     * @return list<LinkSuggestion>
     */
    public function __invoke(ListLinkSuggestions $query): array
    {
        return array_map(
            static fn (LinkSuggestionCandidate $candidate): LinkSuggestion => new LinkSuggestion($candidate->id, $candidate->clinicName, $candidate->city),
            $this->suggestions->of($query->userId),
        );
    }
}
