<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\LinkSuggestion;

/** A clinic holding a client file with the account's mobile: its name and city only. */
final readonly class LinkSuggestionView
{
    public function __construct(
        /** Opaque: names neither the clinic nor a client file. */
        public string $id,
        public ClinicSummaryView $clinic,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['phone']])]
        public string $matchedOn,
    ) {
    }

    public static function of(LinkSuggestion $suggestion): self
    {
        return new self($suggestion->id, new ClinicSummaryView($suggestion->clinicName, $suggestion->city), $suggestion->matchedOn);
    }
}
