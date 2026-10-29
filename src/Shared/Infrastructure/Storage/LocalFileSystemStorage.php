<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LocalFileSystemStorage implements FileStorageInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage')]
        private string $storagePath,
        #[Autowire('%env(default::DEFAULT_URI)%')]
        private string $baseUrl,
    ) {
    }

    public function store(string $filename, string $content): string
    {
        $dir = $this->storagePath;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileIdentifier = date('Y/m/d') . '/' . $filename;
        $fullPath       = $dir . '/' . $fileIdentifier;
        $fileDir        = \dirname($fullPath);

        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        return $fileIdentifier;
    }

    public function storeAt(string $key, string $content): void
    {
        $path = $this->path($key);
        $dir  = \dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create the folder of "%s".', $key));
        }

        if (false === @file_put_contents($path, $content)) {
            throw new \RuntimeException(\sprintf('Cannot write the stored file "%s".', $key));
        }
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function getUrl(string $fileIdentifier): string
    {
        return rtrim($this->baseUrl, '/') . '/storage/' . ltrim($fileIdentifier, '/');
    }

    public function read(string $fileIdentifier): string
    {
        $path    = $this->path($fileIdentifier);
        $content = is_file($path) ? file_get_contents($path) : false;

        if (false === $content) {
            throw new \RuntimeException(\sprintf('No stored file "%s".', $fileIdentifier));
        }

        return $content;
    }

    /**
     * The file of an identifier. An identifier names a file under the storage,
     * never a way out of it.
     */
    private function path(string $fileIdentifier): string
    {
        if (str_contains($fileIdentifier, '..') || '' === trim($fileIdentifier, '/')) {
            throw new \RuntimeException(\sprintf('Invalid file identifier "%s".', $fileIdentifier));
        }

        return $this->storagePath . '/' . ltrim($fileIdentifier, '/');
    }
}
