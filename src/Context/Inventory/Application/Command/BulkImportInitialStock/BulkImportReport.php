<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\BulkImportInitialStock;

final class BulkImportReport
{
    /** @var list<string> */
    private array $created = [];

    /** @var list<string> */
    private array $skipped = [];

    /** @var list<array{ref: string, error: string}> */
    private array $failed = [];

    public function addCreated(string $ref): void
    {
        $this->created[] = $ref;
    }

    public function addSkipped(string $ref): void
    {
        $this->skipped[] = $ref;
    }

    public function addFailure(string $ref, \Throwable $e): void
    {
        $this->failed[] = ['ref' => $ref, 'error' => $e->getMessage()];
    }

    /** @return list<string> */
    public function created(): array
    {
        return $this->created;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return list<array{ref: string, error: string}> */
    public function failed(): array
    {
        return $this->failed;
    }
}
