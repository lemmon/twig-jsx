# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once a stable release is cut.

## [Unreleased]

### Added

- `LICENSE` file (MIT).
- `CHANGELOG.md`.

### Changed

- `composer.lock` is now tracked, for reproducible installs and CI runs.
  Composer ignores library lockfiles when this package is installed as a
  dependency, so consumers are unaffected.
- `.gitignore` now covers common IDE folders (`.idea/`, `.vscode/`,
  `.cursor/`), PHPUnit caches, and the local `reports/` scratch directory.
