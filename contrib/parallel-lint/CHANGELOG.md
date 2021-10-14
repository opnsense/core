# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

[Unreleased]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.3.0...HEAD

## [1.3.1] - 2021-08-13

### Added

- Extend by the Code Climate output format [#50] from [@lukas9393]. 

### Fixed

- PHP 8.1: silence the deprecation notices about missing return types [#64] from [@jrfnl].

### Internal

- Reformat changelog to use reflinks in changelog entries [#58] from [@glensc].

[1.3.1]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.3.0...1.3.1

[#50]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/50
[#58]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/58
[#64]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/64

## [1.3.0] - 2021-04-07

### Added

- Allow for multi-part file extensions to be passed using -e (like `-e php,php.dist`) from [@jrfnl].
- Added syntax error callback [#30] from [@arxeiss].
- Ignore PHP startup errors [#34] from [@jrfnl].
- Restore php 5.3 support [#51] from [@glensc].

### Fixed

- Determine skip lint process failure by status code instead of stderr content [#48] from [@jankonas].

### Changed

- Improve wording in the readme [#52] from [@glensc].

### Internal

- Normalized composer.json from [@OndraM].
- Updated PHPCS dependency from [@jrfnl].
- Cleaned coding style from [@jrfnl].
- Provide one true way to run the test suite [#37] from [@mfn].
- Travis: add build against PHP 8.0 and fix failing test [#41] from [@jrfnl].
- GitHub Actions for testing, and automatic phar creation [#46] from [@roelofr].
- Add .github folder to .gitattributes export-ignore [#54] from [@glensc].
- Suggest to curl composer install via HTTPS [#53] from [@reedy].
- GH Actions: allow for manually triggering a workflow [#55] from [@jrfnl].
- GH Actions: fix phar creation [#55] from [@jrfnl].
- GH Actions: run the tests against all supported PHP versions [#55] from [@jrfnl].
- GH Actions: report CS violations in the PR [#55] from [@jrfnl].

[1.3.0]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.2.0...v1.3.0
[#30]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/30
[#34]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/34
[#37]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/37
[#41]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/41
[#46]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/46
[#48]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/48
[#51]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/51
[#52]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/52
[#53]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/53
[#54]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/54
[#55]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/55

## [1.2.0] - 2020-04-04

### Added

- Added changelog.

### Fixed

- Fixed vendor location for running from other folder from [@Erkens].

### Internal

- Added a .gitattributes file from [@jrfnl], thanks for issue to [@ondrejmirtes].
- Fixed incorrect unit tests from [@jrfnl].
- Fixed minor grammatical errors from [@jrfnl].
- Added Travis: test against nightly (= PHP 8) from [@jrfnl].
- Travis: removed sudo from [@jrfnl].
- Added info about installing like not a dependency.
- Cleaned readme - new organization from previous package.
- Added checklist for new version from [@szepeviktor].

[1.2.0]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.1.0...v1.2.0

[@Erkens]: https://github.com/Erkens
[@OndraM]: https://github.com/OndraM
[@arxeiss]: https://github.com/arxeiss
[@glensc]: https://github.com/glensc
[@jankonas]: https://github.com/jankonas
[@jrfnl]: https://github.com/jrfnl
[@mfn]: https://github.com/mfn
[@ondrejmirtes]: https://github.com/ondrejmirtes
[@reedy]: https://github.com/reedy
[@roelofr]: https://github.com/roelofr
[@szepeviktor]: https://github.com/szepeviktor
[@lukas9393]: https://github.com/lukas9393

