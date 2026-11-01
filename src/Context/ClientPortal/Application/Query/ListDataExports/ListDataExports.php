<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListDataExports;

use App\Shared\Application\Bus\QueryInterface;

/** The exports of an account, latest first. Answers list<DataExportSummary>. */
final readonly class ListDataExports implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
