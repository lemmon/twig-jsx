<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx;

use Twig\Environment;
use Twig\Lexer;
use Twig\Source;
use Twig\TokenStream;

class JSXPreLexer extends Lexer
{
    private array $config;

    public function __construct(Environment $env, array $options = [])
    {
        parent::__construct($env);

        $this->config = array_merge([
            'directory'      => 'components',
            'extension'      => '.twig',
            'prefix'         => '',
            'props_variable' => 'props',
            'content_block'  => 'content',
        ], $options);
    }

    public function tokenize(Source $source): TokenStream
    {
        $code = $this->transform($source->getCode());

        return parent::tokenize(new Source($code, $source->getName(), $source->getPath()));
    }

    /**
     * Rewrite JSX-like component tags into native Twig include/embed calls.
     *
     * Exposed publicly so tests (and external tooling) can assert on the
     * rewritten source without going through the full Twig tokenize/parse
     * pipeline. The actual scanning lives in {@see JsxSourceTransformer};
     * this method exists so callers don't need to know about that class
     * (and so the public surface is one lexer, not two coordinating types).
     */
    public function transform(string $code): string
    {
        return (new JsxSourceTransformer($this->config))->transform($code);
    }
}
