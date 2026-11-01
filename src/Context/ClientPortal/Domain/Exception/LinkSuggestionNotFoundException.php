<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** No suggestion of that id for the account (unknown, forged or no longer suggested): a 404. */
final class LinkSuggestionNotFoundException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Link suggestion not found.');
    }
}
