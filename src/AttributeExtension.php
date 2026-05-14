<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AttributeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('create_attributes', [$this, 'createAttributes']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('render', [$this, 'renderAttributes'], ['is_safe' => ['html']]),
        ];
    }

    public function createAttributes(array $attributes): ComponentAttributes
    {
        return new ComponentAttributes($attributes);
    }

    public function renderAttributes($attributes): string
    {
        return (string) $attributes;
    }
}
