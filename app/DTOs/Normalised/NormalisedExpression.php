<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

final readonly class NormalisedExpression
{
    /**
     * @param  string  $expressionType  'original' | 'translation' | 'adaptation' | 'abridgment'
     * @param  array<int, NormalisedAuthor>  $contributors  Translators, editors, illustrators…
     */
    public function __construct(
        public string $language,
        public string $title,
        public string $expressionType = 'original',
        public array $contributors = [],
    ) {}
}
