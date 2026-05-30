<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx;

/**
 * A smart object that holds all props passed to a component. Renders as HTML
 * attribute pairs when cast to string or used with the `|spread` filter.
 */
class ComponentAttributes implements \Stringable
{
    /** @var array<string, mixed> */
    private array $attributes;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        // @mago-expect lint:no-isset — isset is correct here: Twig's `is defined`
        // check should return false for both missing keys and explicit null values.
        return isset($this->attributes[$name]);
    }

    public function except(string ...$keys): self
    {
        $copy = $this->attributes;
        foreach ($keys as $key) {
            unset($copy[$key]);
        }
        return new self($copy);
    }

    public function __toString(): string
    {
        $html = [];
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $html[] = htmlspecialchars($key);
                continue;
            }
            if ($value !== false && $value !== null) {
                $html[] = sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars((string) $value));
            }
        }

        return implode(' ', $html);
    }
}
