# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

[Unreleased]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.3.2...HEAD

## [1.3.2] - 2022-02-19

### Added

- Support for PHP Console Highlighter 1.0.0, which comes with PHP Console Color 1.0.1, [#92] from [@jrfnl].

### Fixed

- Bug fix: make Phar file run independently of project under scan [#63] from [@jrfnl], fixes [#61].
- Bug fix: checkstyle report could contain invalid XML due to insufficient output escaping [#73] from [@gmazzap], fixes [#72].
- Fix Phar building [#70] from [@jrfnl]. This fixes PHP 8.1 compatibility for the Phar file.
- Documentation fix: the `--show-deprecated` option was missing in both the README as well as the CLI `help` [#84] from [@jrfnl], fixes [#81] reported by [@stronk7].

### Changed

- README: updated information about PHAR availability [#77] from [@jrfnl].
- README: updated CLI example [#80] from [@jrfnl].
- README: added documentation on how to exclude files from a scan based on the PHP version used [#80] from [@jrfnl].
- Composer autoload improvement [#88] from [@jrfnl] with thanks to [@mfn].

### Internal

- Welcome [@jrfnl] as a new maintainer [#32].
- GH Actions: set error reporting to E_ALL [#65], [#76] from [@jrfnl].
- GH Actions: fix failing tests on PHP 5.3-5.5 [#71] from [@jrfnl] and [@villfa].
- GH Actions: auto-cancel concurrent builds [#76] from [@jrfnl].
- GH Actions: testing against PHP 8.2 [#74] from [@grogy].
- GH Actions: release testing against PHP 5.3 [#79] from [@jrfnl].
- GH Actions: update used actions [#82] from [@jrfnl].
- Release checklist can now be found in the `.github` folder [#78] from [@jrfnl].

[1.3.2]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.3.1...v1.3.2

[#32]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/issues/32
[#61]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/issues/61
[#63]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/63
[#65]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/65
[#70]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/70
[#71]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/71
[#72]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/issues/72
[#73]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/73
[#74]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/74
[#76]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/76
[#77]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/77
[#78]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/78
[#79]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/79
[#80]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/80
[#81]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/issues/81
[#82]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/82
[#84]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/84
[#88]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/88
[#89]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/89
[#92]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/pull/92


## [1.3.1] - 2021-08-13

### Added

- Extend by the Code Climate output format [#50] from [@lukas9393]. 

### Fixed

- PHP 8.1: silence the deprecation notices about missing return types [#64] from [@jrfnl].

### Internal

- Reformat changelog to use reflinks in changelog entries [#58] from [@glensc].

[1.3.1]: https://github.com/php-parallel-lint/PHP-Parallel-Lint/compare/v1.3.0...v1.3.1

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
- Add .github folder to .gitattributes export-ignore [#54] from [@reedy].
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
[@gmazzap]: https://github.com/gmazzap
[@jankonas]: https://github.com/jankonas
[@jrfnl]: https://github.com/jrfnl
[@mfn]: https://github.com/mfn
[@ondrejmirtes]: https://github.com/ondrejmirtes
[@reedy]: https://github.com/reedy
[@roelofr]: https://github.com/roelofr
[@stronk7]: https://github.com/stronk7
[@szepeviktor]: https://github.com/szepeviktor
[@lukas9393]: https://github.com/lukas9393
[@villfa]: https://github.com/villfa
[@grogy]: https://github.com/grogy
