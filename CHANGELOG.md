# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once a stable release is cut.

## [Unreleased]

### Added

- Mago static analysis toolchain (`carthage-software/mago ^1.27`):
  - `composer fmt` — reformat `src/` and `tests/` in place
  - `composer lint` — lint the same paths
  - `composer analyze` — static analysis (informational; vendor-class stubs
    are not bundled, so some false-positive `non-existent-class` findings from
    Twig/PHPUnit are expected when run locally)
  - `mago.toml` at the project root pins the formatter style and disables rules
    that don't apply to this codebase (see inline comments).
  - GitHub Actions CI now runs `mago fmt --check` and `mago lint` on every
    push/PR (`mago` job, PHP 8.3).
- `declare(strict_types=1)` in `ComponentAttributes` and `AttributeExtension`.
- `@var array<string, mixed>` annotation on `ComponentAttributes::$attributes`;
  explicit return type `mixed` on `__get`.

### Changed

- **All props now route to a single bag.** Every key the caller passes —
  semantic inputs and HTML fallthrough — arrives in one `ComponentAttributes`
  object (default variable name `props`). The `known_props` routing rule is
  removed; the component template owns the decision of what is a semantic
  input versus a passthrough attribute. Destructure with
  `{% set type = props.type %}` and spread leftovers with
  `{{ props.except('type', ...)|render }}`.
- Config option `attr_name` renamed to `props_variable`; default changed from
  `'attributes'` to `'props'`. Update your `JSXPreLexer` constructor if you
  were passing `attr_name`.
- New config option `content_block` (default `'content'`) — the name of the
  Twig block where a bodied tag's children are rendered. Override at lexer
  init if you prefer `children`, `slot`, etc.

### Removed

- `known_props` config option removed. All props go into the single bag.

### Added

- `LICENSE` file (MIT).
- `CHANGELOG.md`.
- PHPUnit-based test suite (`tests/LexerTransformTest`, `tests/RenderTest`)
  with `phpunit.xml.dist`, and a `composer test` script.
- GitHub Actions CI: `composer validate --strict`, PHPUnit on PHP 8.1–8.4,
  and a `prefer-lowest` job to catch loose dependency constraints.
- `JSXPreLexer::transform(string $code): string` — a public seam that returns
  the rewritten Twig source without going through the full tokenize pipeline.
  Used by the test suite; will be reused by the upcoming scanner-based
  rewrite.

### Changed

- `composer.lock` is now tracked, for reproducible installs and CI runs.
  Composer ignores library lockfiles when this package is installed as a
  dependency, so consumers are unaffected.
- `.gitignore` now covers common IDE folders (`.idea/`, `.vscode/`,
  `.cursor/`), PHPUnit caches, and the local `reports/` scratch directory.
- Bumped minimum PHP requirement from `>=8.0` to `^8.1`. PHP 8.0 has been
  end-of-life since November 2023; the bump is a prerequisite for the
  maintained PHPUnit 10 line.

### Removed

- `lemmon/clsx` is no longer a dependency, and `AttributeExtension` no
  longer registers a `clsx` Twig function. The demo's `Alert.twig` was
  rewritten to use plain conditional class concatenation. Anyone who wants
  a class-merging helper can register one on their own Twig environment.

### Fixed

- Static prop values containing apostrophes or backslashes
  (`<Alert message="It's mine" />`, `<Alert data-path="a\b" />`) no longer
  produce invalid Twig source. The values are now escaped before being
  spliced into the generated single-quoted Twig string literal.

### Changed

- **Call-site syntax flipped from Vue-style to Svelte/JSX-style.** Use
  `name="literal"` for static strings and `name={expression}` for Twig
  expressions; the `:` prefix is gone. Shorthand `{foo}` desugars to
  `foo={foo}`. Bare attributes (`disabled`) still mean `true`.
  The old `:foo` syntax now throws `Twig\Error\SyntaxError` with a
  pointer to the new form rather than silently passing through.
- **Replaced the two-pass regex preprocessor with a single-pass
  character scanner** (`JsxSourceTransformer`). The regex
  implementation had four structural blind spots that the scanner now
  handles correctly:
  - tags inside Twig string literals, Twig comments, and HTML comments
    are passed through verbatim instead of being rewritten;
  - same-name nested tags (`<Alert><Alert/></Alert>`) track depth
    properly instead of matching the first close;
  - `>` inside attribute string values (`<Alert title="a>b" />`) no
    longer truncates the tag;
  - brace expressions correctly balance against `{`/`}` while ignoring
    braces inside Twig string literals (`foo={'a}b'}`, `foo={ {k: 'v'} }`).
- Unquoted attribute values (`foo=bar`) and unclosed tags now produce
  clear `Twig\Error\SyntaxError`s instead of silently malformed output.
- Both single- and double-quoted string attribute values are now
  accepted (`type='info'` and `type="info"` are equivalent). The old
  regex only accepted double quotes.
