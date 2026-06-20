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

    public function getUrl(string $fileIdentifier): string
    {
        return rtrim($this->baseUrl, '/') . '/storage/' . ltrim($fileIdentifier, '/');
    }
}
