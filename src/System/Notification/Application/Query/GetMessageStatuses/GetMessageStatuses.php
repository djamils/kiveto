<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetMessageStatuses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Where these messages stand (e.g. the invoice history), by message id;
 * unknown ids are left out.
 */
final readonly class GetMessageStatuses implements QueryInterface
{
    /**
     * @param list<string> $messageIds
     */
    public function __construct(public array $messageIds)
    {
    }
}
