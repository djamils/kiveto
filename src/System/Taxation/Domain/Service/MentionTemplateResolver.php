<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Service;

use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\LegalMention;
use App\System\Taxation\Domain\ValueObject\LegalMentions;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class MentionTemplateResolver
{
    public function __construct(private MentionTemplateRepositoryInterface $repo)
    {
    }

    public function findApplicable(TaxRegimeId $regimeId, FiscalContext $context, AppliedTaxRate $rate): LegalMentions
    {
        $templates = $this->repo->findActiveByRegime($regimeId);
        $mentions  = [];

        foreach ($templates as $template) {
            if (!$template->condition()->matches($context)) {
                continue;
            }

            $text = str_replace('{{rate}}', $rate->value()->toPercentString(), $template->template());
            $text = str_replace('{{regime}}', $regimeId->toString(), $text);
            $text = str_replace('{{country}}', $context->country()->toString(), $text);

            $mentions[] = new LegalMention($template->code(), $text, $template->locale());
        }

        return LegalMentions::fromList(...$mentions);
    }
}
