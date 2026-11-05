<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * A query whose answer the query bus may keep for the rest of the request:
 * the reference data a page reads several times over (the shell, the page,
 * the sidebar), such as the clinic's settings or its practitioners.
 *
 * The answer is never kept while a command runs, and is forgotten once a
 * command has run and between requests (and worker messages): only a query
 * whose answer a command alone can change belongs here.
 */
interface RequestCacheableQueryInterface extends QueryInterface
{
}
