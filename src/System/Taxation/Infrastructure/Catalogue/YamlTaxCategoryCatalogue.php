<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Catalogue;

use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the tax categories shipped in `Resources/categories.yaml` as the
 * commands that register them.
 *
 * Every category must state its nature (goods or services) explicitly: a
 * missing or unknown nature is refused rather than defaulted, since it
 * drives how invoices are framed for the tax authority.
 */
final readonly class YamlTaxCategoryCatalogue
{
    private const string FILE = '/src/System/Taxation/Infrastructure/Resources/categories.yaml';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    /**
     * @return list<RegisterTaxCategory>
     */
    public function commands(): array
    {
        return $this->parse($this->projectDir . self::FILE);
    }

    /**
     * @return list<RegisterTaxCategory>
     */
    public function parse(string $path): array
    {
        $data = Yaml::parseFile($path);

        if (!\is_array($data) || !\is_array($data['categories'] ?? null)) {
            throw new \UnexpectedValueException(\sprintf('"%s" has no "categories" list.', $path));
        }

        $commands = [];

        foreach ($data['categories'] as $category) {
            if (!\is_array($category) || !\is_string($category['code'] ?? null) || !\is_string($category['displayName'] ?? null)) {
                throw new \UnexpectedValueException(\sprintf('"%s" holds a category without code or display name.', $path));
            }

            $nature      = $category['nature'] ?? null;
            $description = $category['description'] ?? null;
            $resolved    = \is_string($nature) ? TaxCategoryNature::tryFrom($nature) : null;

            if (null === $resolved) {
                throw new \UnexpectedValueException(\sprintf(
                    'Tax category "%s" must declare its nature: "goods" or "services".',
                    $category['code'],
                ));
            }

            $commands[] = new RegisterTaxCategory(
                TaxCategoryCode::fromString($category['code']),
                $category['displayName'],
                \is_string($description) ? $description : null,
                $resolved,
            );
        }

        return $commands;
    }
}
