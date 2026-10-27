<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\DeleteMessageFiles;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Deletes the Notification-owned files of purged messages (`async`, queued
 * in the purge transaction: runs only once the purge is committed).
 */
final readonly class DeleteMessageFiles implements CommandInterface
{
    /**
     * @param list<string> $storageKeys
     */
    public function __construct(public array $storageKeys)
    {
    }
}
