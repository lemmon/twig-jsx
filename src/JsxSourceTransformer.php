<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx;

use Twig\Error\SyntaxError;

/**
 * Character-by-character scanner that rewrites JSX-like component tags
 * (`<Component foo="x" bar={expr} />`) into native Twig `{% include %}`
 * and `{% embed %}` calls.
 *
 * Replaces the original two-pass regex preprocessor, which had several
 * structural blind spots:
 *
 *  - regex `.*?` body matching couldn't track depth for same-name nested
 *    tags;
 *  - the `[^>]*?` attribute capture broke on `>` inside attribute values;
 *  - tags inside Twig string literals, Twig comments, and HTML comments
 *    were transformed when they should have been passed through.
 *
 * The scanner walks the source exactly once, maintaining a small set of
 * positional sub-modes (Twig construct, HTML comment, attribute string,
 * attribute brace expression) so each of those cases is handled
 * explicitly.
 *
 * Output is plain Twig source; the lexer hands it back to {@see \Twig\Lexer}
 * for normal tokenizing. No template execution happens here.
 *
 * @internal Tests target this directly via {@see JSXPreLexer::transform()}.
 */
final class JsxSourceTransformer
{
    private string $code = '';
    private int $pos = 0;
    private int $len = 0;

    private readonly string $directory;
    private readonly string $extension;
    private readonly string $prefix;
    /** @var list<string> */
    private readonly array $knownProps;
    private readonly string $attrName;
    private readonly string $tagNamePattern;

    /**
     * @param array{
     *     directory: string,
     *     extension: string,
     *     prefix: string,
     *     known_props: list<string>,
     *     attr_name: string,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->directory = $config['directory'];
        $this->extension = $config['extension'];
        $this->prefix = $config['prefix'];
        $this->knownProps = $config['known_props'];
        $this->attrName = $config['attr_name'];

        $prefixQuoted = preg_quote($this->prefix, '/');
        $this->tagNamePattern = $prefixQuoted === ''
            ? '[A-Z][a-zA-Z0-9]*'
            : $prefixQuoted . '[a-zA-Z0-9]+';
    }

    public function transform(string $code): string
    {
        $this->code = $code;
        $this->pos = 0;
        $this->len = strlen($code);

        return $this->scanUntil(null);
    }

    /**
     * Walk forward consuming and transforming source. When $endTagName is
     * non-null, stop at the matching `</Name>` close; otherwise stop at EOF.
     *
     * Same-name nested tags work naturally: each nested `<Name>` is consumed
     * by a recursive {@see consumeJsxTag()} call, whose body scan operates
     * in its own stack frame and so doesn't interfere with the outer
     * frame's end-tag search.
     */
    private function scanUntil(?string $endTagName): string
    {
        $out = '';

        while ($this->pos < $this->len) {
            if ($endTagName !== null && $this->matchAt('</' . $endTagName . '>')) {
                $this->pos += strlen('</' . $endTagName . '>');
                return $out;
            }

            $c = $this->code[$this->pos];

            if ($c === '<') {
                if ($this->matchAt('<!--')) {
                    $out .= $this->consumeHtmlComment();
                    continue;
                }
                if ($this->tagNameAt($this->pos + 1) !== null) {
                    $out .= $this->consumeJsxTag();
                    continue;
                }
                // Plain `<` (e.g. start of lowercase HTML tag, or stray `<`).
                $out .= $c;
                $this->pos++;
                continue;
            }

            if ($c === '{') {
                $next = $this->code[$this->pos + 1] ?? '';
                if ($next === '{' || $next === '%' || $next === '#') {
                    $out .= $this->consumeTwigConstruct();
                    continue;
                }
            }

            $out .= $c;
            $this->pos++;
        }

        if ($endTagName !== null) {
            throw new SyntaxError(sprintf('Unclosed JSX tag <%s>.', $endTagName));
        }

        return $out;
    }

    /**
     * Consume a `<Tag ...>` or `<Tag ... />` starting at the current `<`.
     * For bodied tags, recursively transforms the body and emits an
     * `{% embed %}{% block content %}...{% endblock %}{% endembed %}`.
     */
    private function consumeJsxTag(): string
    {
        $this->pos++; // consume `<`

        $tagName = $this->tagNameAt($this->pos);
        if ($tagName === null) {
            // Should never happen — caller checks first.
            throw new SyntaxError('Internal: consumeJsxTag called with no tag.');
        }
        $this->pos += strlen($tagName);

        $propsCode = $this->parseAttributes();

        $resolvedName = $this->resolveName($tagName);
        $templatePath = $this->directory . '/' . $resolvedName . $this->extension;

        if ($this->matchAt('/>')) {
            $this->pos += 2;
            return "{% include '{$templatePath}' with {$propsCode} %}";
        }

        if ($this->pos < $this->len && $this->code[$this->pos] === '>') {
            $this->pos++;
            $body = $this->scanUntil($tagName);
            return "{% embed '{$templatePath}' with {$propsCode} %}"
                . "{% block content %}{$body}{% endblock %}"
                . '{% endembed %}';
        }

        throw new SyntaxError(sprintf(
            "Expected '>' or '/>' to close <%s>; got %s.",
            $tagName,
            $this->describeHere(),
        ));
    }

    /**
     * Parse zero or more attributes and stop at `/` or `>` (which the
     * caller then consumes). Returns the full `{...}` props hash string
     * (already including the trailing `'attributes': create_attributes(...)`
     * sub-bag).
     */
    private function parseAttributes(): string
    {
        $props = [];
        $attributes = [];

        while ($this->pos < $this->len) {
            $this->skipWhitespace();
            if ($this->pos >= $this->len) {
                break;
            }

            $c = $this->code[$this->pos];

            if ($c === '/' || $c === '>') {
                break;
            }

            // `{identifier}` shorthand — same name as variable.
            //
            // Brace forms (this shorthand and `name={expr}` below) always
            // route to the props bag, regardless of `known_props` membership.
            // This preserves the old `:foo` semantic ("this is a real prop")
            // for one more chunk; #8 erases the asymmetry by sending every
            // key to a single bag.
            if ($c === '{') {
                [$key, $expr] = $this->parseBraceShorthand();
                $props[] = "'{$key}': {$expr}";
                continue;
            }

            // Old `:foo` syntax — be loud, not silent.
            if ($c === ':') {
                throw new SyntaxError(
                    "The ':foo' attribute syntax is no longer supported; "
                    . "use foo={expression} for expressions, {foo} for shorthand, "
                    . 'or foo="literal" for static strings.',
                );
            }

            $key = $this->matchAttributeName();
            if ($key === null) {
                throw new SyntaxError(sprintf(
                    'Expected attribute name; got %s.',
                    $this->describeHere(),
                ));
            }
            $this->pos += strlen($key);

            if ($this->pos < $this->len && $this->code[$this->pos] === '=') {
                $this->pos++;
                [$kind, $value] = $this->parseAttributeValue($key);
                if ($kind === 'string') {
                    $literal = "'" . $this->escapeStaticValue($value) . "'";
                    if (in_array($key, $this->knownProps, true)) {
                        $props[] = "'{$key}': {$literal}";
                    } else {
                        $attributes[] = "'{$key}': {$literal}";
                    }
                } else {
                    // Brace expression form: always to props (see comment
                    // on the `{identifier}` shorthand branch above).
                    $props[] = "'{$key}': {$value}";
                }
                continue;
            }

            // Bare attribute → boolean true.
            if (in_array($key, $this->knownProps, true)) {
                $props[] = "'{$key}': true";
            } else {
                $attributes[] = "'{$key}': true";
            }
        }

        $props[] = "'{$this->attrName}': create_attributes({" . implode(', ', $attributes) . '})';

        return '{' . implode(', ', $props) . '}';
    }

    /**
     * Parse `{identifier}` at attribute position. Only a bare identifier
     * is allowed in shorthand form — anything more elaborate must use the
     * explicit `name={expression}` form so the resulting variable name is
     * unambiguous.
     *
     * @return array{0: string, 1: string} [name, expression] (both equal for shorthand)
     */
    private function parseBraceShorthand(): array
    {
        $start = $this->pos;
        $this->pos++; // consume `{`
        $this->skipWhitespace();

        $identifier = $this->matchIdentifier();
        if ($identifier === null) {
            $this->pos = $start;
            throw new SyntaxError(
                "Expected '{identifier}' shorthand; for an expression value, "
                . 'use name={expression} instead.',
            );
        }
        $this->pos += strlen($identifier);
        $this->skipWhitespace();

        if ($this->pos >= $this->len || $this->code[$this->pos] !== '}') {
            $this->pos = $start;
            throw new SyntaxError(
                "Expected '}' after shorthand identifier '{$identifier}'.",
            );
        }
        $this->pos++; // consume `}`

        return [$identifier, $identifier];
    }

    /**
     * Parse the value after `=`. Returns either a literal string or a
     * brace-balanced Twig expression. Unquoted values (`foo=bar`) are
     * rejected.
     *
     * @return array{0: 'string'|'expr', 1: string}
     */
    private function parseAttributeValue(string $forAttribute): array
    {
        if ($this->pos >= $this->len) {
            throw new SyntaxError(sprintf(
                "Expected value after '=' for attribute '%s'; got end of source.",
                $forAttribute,
            ));
        }

        $c = $this->code[$this->pos];

        if ($c === '"' || $c === "'") {
            $quote = $c;
            $this->pos++;
            $start = $this->pos;
            while ($this->pos < $this->len && $this->code[$this->pos] !== $quote) {
                $this->pos++;
            }
            if ($this->pos >= $this->len) {
                throw new SyntaxError(sprintf(
                    "Unterminated string value for attribute '%s'.",
                    $forAttribute,
                ));
            }
            $value = substr($this->code, $start, $this->pos - $start);
            $this->pos++; // consume closing quote
            return ['string', $value];
        }

        if ($c === '{') {
            $this->pos++; // consume `{`
            $expr = $this->parseBraceExpression($forAttribute);
            return ['expr', trim($expr)];
        }

        throw new SyntaxError(sprintf(
            "Unquoted attribute values are not supported; use %s=\"literal\" or %s={expression}.",
            $forAttribute,
            $forAttribute,
        ));
    }

    /**
     * Scan a brace-balanced expression body starting just after `{`.
     * Twig string literals (`'…'`, `"…"`) inside the expression do not
     * count toward brace depth, so `foo={ {a: 'b}'} }` works.
     */
    private function parseBraceExpression(string $forAttribute): string
    {
        $start = $this->pos;
        $depth = 1;

        while ($this->pos < $this->len) {
            $c = $this->code[$this->pos];

            if ($c === '"' || $c === "'") {
                $this->skipTwigString($c);
                continue;
            }

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $expr = substr($this->code, $start, $this->pos - $start);
                    $this->pos++; // consume closing `}`
                    return $expr;
                }
            }

            $this->pos++;
        }

        throw new SyntaxError(sprintf(
            "Unclosed brace expression in attribute '%s'.",
            $forAttribute,
        ));
    }

    /**
     * Pass through a `{{ ... }}`, `{% ... %}`, or `{# ... #}` Twig
     * construct verbatim. String literals inside output/block tags are
     * respected so a `}}` inside `'...'` doesn't end the construct early.
     */
    private function consumeTwigConstruct(): string
    {
        $start = $this->pos;
        $marker = $this->code[$this->pos + 1];

        if ($marker === '#') {
            $end = strpos($this->code, '#}', $this->pos + 2);
            if ($end === false) {
                throw new SyntaxError('Unclosed Twig comment {# ... #}.');
            }
            $this->pos = $end + 2;
            return substr($this->code, $start, $this->pos - $start);
        }

        $endMarker = $marker === '{' ? '}}' : '%}';
        $this->pos += 2;

        while ($this->pos < $this->len) {
            $c = $this->code[$this->pos];
            if ($c === '"' || $c === "'") {
                $this->skipTwigString($c);
                continue;
            }
            if ($this->matchAt($endMarker)) {
                $this->pos += 2;
                return substr($this->code, $start, $this->pos - $start);
            }
            $this->pos++;
        }

        throw new SyntaxError(sprintf('Unclosed Twig construct (expected %s).', $endMarker));
    }

    /**
     * Pass through `<!-- ... -->` verbatim, including any JSX-looking
     * markup inside. If the closing `-->` is missing we leniently swallow
     * to EOF rather than throwing — HTML parsers do the same.
     */
    private function consumeHtmlComment(): string
    {
        $start = $this->pos;
        $end = strpos($this->code, '-->', $this->pos + 4);
        if ($end === false) {
            $this->pos = $this->len;
            return substr($this->code, $start);
        }
        $this->pos = $end + 3;

        return substr($this->code, $start, $this->pos - $start);
    }

    /**
     * Advance past a single-quoted or double-quoted Twig string literal
     * starting at the current opening quote. Handles `\\` and `\$quote`
     * escapes so an escaped quote doesn't end the scan prematurely.
     */
    private function skipTwigString(string $quote): void
    {
        $this->pos++; // opening quote
        while ($this->pos < $this->len) {
            $c = $this->code[$this->pos];
            if ($c === '\\' && $this->pos + 1 < $this->len) {
                $this->pos += 2;
                continue;
            }
            if ($c === $quote) {
                $this->pos++;
                return;
            }
            $this->pos++;
        }

        throw new SyntaxError('Unterminated string literal.');
    }

    private function matchAt(string $needle): bool
    {
        return substr_compare($this->code, $needle, $this->pos, strlen($needle)) === 0;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && ctype_space($this->code[$this->pos])) {
            $this->pos++;
        }
    }

    /**
     * If a JSX tag name starts at $offset, return it; otherwise return null.
     * Matches against the configured prefix/case-rule.
     */
    private function tagNameAt(int $offset): ?string
    {
        if ($offset >= $this->len) {
            return null;
        }
        $slice = substr($this->code, $offset, 64);
        if (preg_match('/^(' . $this->tagNamePattern . ')\b/', $slice, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function matchIdentifier(): ?string
    {
        $slice = substr($this->code, $this->pos, 64);
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', $slice, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    private function matchAttributeName(): ?string
    {
        $slice = substr($this->code, $this->pos, 64);
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*/', $slice, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    private function resolveName(string $tagName): string
    {
        if ($this->prefix !== '' && str_starts_with($tagName, $this->prefix)) {
            return substr($tagName, strlen($this->prefix));
        }

        return $tagName;
    }

    private function escapeStaticValue(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', "'" => "\\'"]);
    }

    /**
     * Short human-readable hint for the next few chars, used in error
     * messages. Keeps templates rendered in tests/CI debuggable without
     * dumping the entire source.
     */
    private function describeHere(): string
    {
        if ($this->pos >= $this->len) {
            return 'end of source';
        }
        $snippet = substr($this->code, $this->pos, 16);

        return "'" . str_replace("\n", '\n', $snippet) . "'";
    }
}
