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
     * Stores content under an exact key, overwriting what is there and creating
     * the folders it needs. A retry writes the same file again: no orphan.
     *
     * @throws \RuntimeException when the key would leave the storage or the file cannot be written
     */
    public function storeAt(string $key, string $content): void;

    /**
     * Removes a stored file; a key with no file is not an error.
     *
     * @throws \RuntimeException when the key would leave the storage
     */
    public function delete(string $key): void;

    /**
     * Returns the public URL or download path for a stored file identifier.
     */
    public function getUrl(string $fileIdentifier): string;

    /**
     * The content of a stored file.
     *
     * @throws \RuntimeException when there is no such file
     */
    public function read(string $fileIdentifier): string;
}
