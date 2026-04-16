<?php

namespace Lemmon\TwigJsx;

/**
 * A smart object that holds HTML attributes and can be printed directly.
 */
class ComponentAttributes implements \Stringable
{
    private array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Allows accessing attributes as array keys: attributes.class
     */
    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Allows checking if an attribute exists: attributes.class is defined
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns a new instance without the specified keys.
     * Useful for: {{ attributes.defaults({class: 'btn'})|render }}
     */
    public function except(string ...$keys): self
    {
        $copy = $this->attributes;
        foreach ($keys as $key) {
            unset($copy[$key]);
        }
        return new self($copy);
    }

    /**
     * The magic happens here. 
     * Twig calls this when you do {{ attributes }}
     */
    public function __toString(): string
    {
        $html = [];
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $html[] = htmlspecialchars($key);
            } elseif ($value !== false && $value !== null) {
                $html[] = sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars((string) $value));
            }
        }

        return implode(' ', $html);
    }
}
