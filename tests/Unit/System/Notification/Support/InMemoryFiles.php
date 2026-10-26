<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\Shared\Domain\Storage\FileStorageInterface;

final class InMemoryFiles implements FileStorageInterface
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    public function store(string $filename, string $content): string
    {
        $this->files[$filename] = $content;

        return $filename;
    }

    public function storeAt(string $key, string $content): void
    {
        $this->files[$key] = $content;
    }

    public function delete(string $key): void
    {
        $this->deleted[] = $key;
        unset($this->files[$key]);
    }

    public function getUrl(string $fileIdentifier): string
    {
        return '/files/' . $fileIdentifier;
    }

    public function read(string $fileIdentifier): string
    {
        return $this->files[$fileIdentifier] ?? throw new \RuntimeException(\sprintf('No file "%s".', $fileIdentifier));
    }
}
