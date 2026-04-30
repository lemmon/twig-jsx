# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once a stable release is cut.

## [Unreleased]

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
