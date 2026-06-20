<?php

declare(strict_types=1);

namespace App\Shared\Domain\Storage;

interface FileStorageInterface
{
    /**
     * Stores file content and returns a file identifier (e.g., relative path or key).
     */
    public function store(string $filename, string $content): string;

    /**
     * Returns the public URL or download path for a stored file identifier.
     */
    public function getUrl(string $fileIdentifier): string;
}
