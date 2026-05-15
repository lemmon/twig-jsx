# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once a stable release is cut.

## [Unreleased]

## [0.1.0] - 2026-05-15

Initial release.

### Added

- **JSX-like component syntax for Twig.** `<Alert type="info" />` and
  `<Alert>…</Alert>` tags are rewritten into native Twig `{% include %}` and
  `{% embed %}` calls at the lexer level — no runtime overhead, no Symfony
  dependency.
- **Four call-site forms**, all routed into a single `props` bag:
  - `<Alert type="info" />` — static string
  - `<Alert type={expression} />` — Twig expression
  - `<Alert {type} />` — shorthand, equivalent to `type={type}`
  - `<Alert important />` — bare boolean, equivalent to `important={true}`
- **`ComponentAttributes` props bag** with:
  - `props.key` property access (returns `null` for missing keys; Twig's
    `is defined` check works via `__isset`)
  - `props.except('a', 'b', …)` — new bag without listed keys, for HTML
    fallthrough spreading
  - `{{ props|render }}` filter — renders entries as `key="value"` HTML
    attribute pairs, with `htmlspecialchars` on both sides
- **Bodied tags** render children into a Twig block (default `content`),
  configurable via the `content_block` option.
- **Single-pass character scanner** (`JsxSourceTransformer`) handles the
  cases a naive regex preprocessor gets wrong:
  - tags inside Twig string literals, `{# … #}` comments, and `<!-- … -->`
    are passed through verbatim;
  - same-name nested tags (`<Alert><Alert/></Alert>`) track depth correctly;
  - `>` inside attribute string values does not truncate the tag;
  - brace expressions balance `{`/`}` while respecting Twig string literals
    (`foo={'a}b'}`, `foo={ {k: 'v'} }`);
  - static prop values escape `\` and `'` before being spliced into the
    generated single-quoted Twig literal.
- **Loud `Twig\Error\SyntaxError`s** for unquoted attribute values,
  unclosed tags, unterminated strings, and unclosed brace expressions.
- **Configuration options** on `JSXPreLexer`:

  | Option | Default | Description |
  |---|---|---|
  | `directory` | `components` | Subdirectory inside `templates/` |
  | `extension` | `.twig` | File extension for component templates |
  | `prefix` | `""` | Tag prefix; when empty, any Capitalized tag is treated as a component |
  | `props_variable` | `props` | Name of the props bag inside the component template |
  | `content_block` | `content` | Twig block name where a bodied tag's children are rendered |

- **PHPUnit 10 test suite** (`LexerTransformTest`, `RenderTest`) and a
  `composer test` script.
- **Mago static-analysis toolchain** (`composer format`, `format:check`,
  `lint`, `analyze`, `check`) with a `mago.toml` at the project root.
- **GitHub Actions CI**: `composer validate --strict`, PHPUnit on PHP
  8.1–8.4, `mago fmt --check` + `mago lint`, and a `prefer-lowest` job to
  catch loose dependency constraints.
- **`llms.txt`** at the project root — machine-readable summary for LLM
  tooling.
- **`LICENSE`** (MIT).

### Requirements

- PHP `^8.1`
- Twig `^3.0`

[Unreleased]: https://github.com/lemmon/twig-jsx/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/lemmon/twig-jsx/releases/tag/v0.1.0
