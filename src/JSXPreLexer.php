<?php

namespace Lemmon\TwigJsx;

use Twig\Lexer;
use Twig\Source;
use Twig\TokenStream;
use Twig\Environment;

class JSXPreLexer extends Lexer
{
    private array $config;

    public function __construct(Environment $env, array $options = [])
    {
        parent::__construct($env);
        
        // Merge defaults with user options
        $this->config = array_merge([
            'directory'   => 'components', 
            'extension'   => '.twig',
            'prefix'      => '',           
            'known_props' => ['title', 'message', 'type', 'important'], 
            'attr_name'   => 'attributes',
        ], $options);
    }

    public function tokenize(Source $source): TokenStream
    {
        $code = $source->getCode();
        $prefix = preg_quote($this->config['prefix'], '/');
        $tagPattern = ($prefix === '') ? '[A-Z][a-zA-Z0-9]*' : $prefix . '[a-zA-Z0-9]+';

        // 1. Self-closing tags: <Component />
        $code = preg_replace_callback("/<(?P<name>{$tagPattern})\s*(?P<props>[^>]*?)\s*\/>/", function($matches) {
            $name = $this->resolveName($matches['name']);
            $props = $this->parseProps($matches['props']);
            return "{% include '{$this->config['directory']}/{$name}{$this->config['extension']}' with {$props} %}";
        }, $code);

        // 2. Tags with body: <Component>...</Component>
        $code = preg_replace_callback("/<(?P<name>{$tagPattern})\s*(?P<props>[^>]*?)>(?P<content>.*?)<\/\\1>/s", function($matches) {
            $name = $this->resolveName($matches['name']);
            $props = $this->parseProps($matches['props']);
            $content = $matches['content'];
            return "{% embed '{$this->config['directory']}/{$name}{$this->config['extension']}' with {$props} %}{% block content %}{$content}{% endblock %}{% endembed %}";
        }, $code);

        return parent::tokenize(new Source($code, $source->getName(), $source->getPath()));
    }

    private function resolveName(string $tagName): string 
    {
        if ($this->config['prefix'] !== '' && str_starts_with($tagName, $this->config['prefix'])) {
            return substr($tagName, strlen($this->config['prefix']));
        }
        return $tagName;
    }

    private function parseProps(string $propsString): string
    {
        $props = [];
        $attributes = [];
        
        // Revised Regex: Matches key="value", :key="value", or just :key / key
        preg_match_all('/(?P<key>[:\w-]+)(?:="(?P<value>[^"]*)")?/', $propsString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match['key'];
            $value = $match['value'] ?? null;

            if (str_starts_with($key, ':')) {
                $realKey = ltrim($key, ':');
                if ($value === null) {
                    // Shorthand Variable: :foo -> 'foo': foo
                    $props[] = "'{$realKey}': {$realKey}";
                } else {
                    // Dynamic Prop: :foo="bar" -> 'foo': bar
                    $props[] = "'{$realKey}': {$value}";
                }
            } else {
                if ($value === null) {
                    // Shorthand Boolean: important -> 'important': true
                    if (in_array($key, $this->config['known_props'])) {
                        $props[] = "'{$key}': true";
                    } else {
                        // For attributes, a value-less attribute is just true
                        $attributes[] = "'{$key}': true";
                    }
                } elseif (in_array($key, $this->config['known_props'])) {
                    $props[] = "'{$key}': '{$value}'";
                } else {
                    $attributes[] = "'{$key}': '{$value}'";
                }
            }
        }

        $props[] = "'{$this->config['attr_name']}': create_attributes({" . implode(', ', $attributes) . "})";

        return '{' . implode(', ', $props) . '}';
    }
}
