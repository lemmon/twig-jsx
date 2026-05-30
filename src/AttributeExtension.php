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
        // Marked is_safe=html because ComponentAttributes::__toString() runs
        // htmlspecialchars on every key and value before emitting.
        //
        // Named `spread` for the JSX analogy: `{{ props|spread }}` renders the
        // bag onto the element exactly like `<div {...props}>` in JSX.
        return [
            new TwigFilter('spread', [$this, 'spreadAttributes'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createAttributes(array $attributes): ComponentAttributes
    {
        return new ComponentAttributes($attributes);
    }

    public function spreadAttributes(\Stringable|string $attributes): string
    {
        return (string) $attributes;
    }
}
