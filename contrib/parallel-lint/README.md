# PHP Parallel Lint

[![Downloads this Month](https://img.shields.io/packagist/dm/php-parallel-lint/php-parallel-lint.svg)](https://packagist.org/packages/php-parallel-lint/php-parallel-lint)
[![Build Status](https://github.com/php-parallel-lint/PHP-Parallel-Lint/actions/workflows/test.yml/badge.svg)](https://github.com/php-parallel-lint/PHP-Parallel-Lint/actions/workflows/test.yml)
[![License](https://poser.pugx.org/php-parallel-lint/php-parallel-lint/license.svg)](https://packagist.org/packages/php-parallel-lint/php-parallel-lint)

This application checks syntax of PHP files in parallel.
It can output in plain text, colored text, json and checksyntax formats.
Additionally `blame` can be used to show commits that introduced the breakage.

Running parallel jobs in PHP is inspired by Nette framework tests.

The application is officially supported for use with PHP 5.3 to 8.1.

## Table of contents

1. [Installation](#installation)
2. [Example output](#example-output)
3. [History](#history)
4. [Command line options](#command-line-options)
5. [Recommended excludes for Symfony framework](#recommended-excludes-for-symfony-framework)
6. [Excluding files from a scan based on the PHP version used](#excluding-files-from-a-scan-based-on-the-php-version-used)
7. [How to upgrade](#how-to-upgrade)

## Installation

Install with `composer` as development dependency:

    composer require --dev php-parallel-lint/php-parallel-lint

Alternatively you can install as a standalone `composer` project:

    composer create-project php-parallel-lint/php-parallel-lint /path/to/folder/php-parallel-lint
    /path/to/folder/php-parallel-lint/parallel-lint # running tool

For colored output, install the suggested package `php-parallel-lint/php-console-highlighter`:

    composer require --dev php-parallel-lint/php-console-highlighter

Since v1.3.0, a PHAR file is also made available for each release.
This PHAR file is published as an asset for each release and can be found on the [Releases](https://github.com/php-parallel-lint/PHP-Parallel-Lint/releases) page.

## Example output

![Example use of tool with error](/tests/examples/example-images/use-error.png?raw=true "Example use of tool with error")


## History

This project was originally created by [@JakubOnderka] and released as
[jakub-onderka/php-parallel-lint].

Since then, Jakub has moved on to other interests and as of January 2020, the
second most active maintainer [@grogy] has taken over maintenance of the project
and given the project - and related dependencies - a new home in the PHP
Parallel Lint organisation.

It is strongly recommended for existing users of the (unmaintained)
[jakub-onderka/php-parallel-lint] package to switch their dependency to
[php-parallel-lint/php-parallel-lint], see [How to upgrade](#how-to-upgrade) below.

[php-parallel-lint/php-parallel-lint]: https://github.com/php-parallel-lint/PHP-Parallel-Lint
[grogy/php-parallel-lint]: https://github.com/grogy/PHP-Parallel-Lint
[jakub-onderka/php-parallel-lint]: https://github.com/JakubOnderka/PHP-Parallel-Lint
[@JakubOnderka]: https://github.com/JakubOnderka
[@grogy]: https://github.com/grogy

## Command line options

- `-p <php>`                Specify PHP-CGI executable to run (default: 'php').
- `-s`, `--short`           Set short_open_tag to On (default: Off).
- `-a`, `--asp`             Set asp_tags to On (default: Off).
- `-e <ext>`                Check only files with selected extensions separated by comma. (default: php,php3,php4,php5,phtml,phpt)
- `-j <num>`                Run <num> jobs in parallel (default: 10).
- `--exclude`               Exclude a file or directory. If you want exclude multiple items, use multiple exclude parameters.
- `--colors`                Enable colors in console output. (disables auto detection of color support)
- `--no-colors`             Disable colors in console output.
- `--no-progress`           Disable progress in console output.
- `--checkstyle`            Output results as Checkstyle XML.
- `--json`                  Output results as JSON string (requires PHP 5.4).
- `--gitlab`                Output results for the GitLab Code Quality Widget (requires PHP 5.4), see more in [Code Quality](https://docs.gitlab.com/ee/user/project/merge_requests/code_quality.html) documentation..
- `--blame`                 Try to show git blame for row with error.
- `--git <git>`             Path to Git executable to show blame message (default: 'git').
- `--stdin`                 Load files and folder to test from standard input.
- `--ignore-fails`          Ignore failed tests.
- `--show-deprecated`       Show deprecations (default: Off).
- `--syntax-error-callback` File with syntax error callback for ability to modify error, see more in [example](doc/syntax-error-callback.md).
- `-h`, `--help`            Print this help.
- `-V`, `--version`         Display the application version


## Recommended excludes for Symfony framework

To run from the command line:

    vendor/bin/parallel-lint --exclude .git --exclude app --exclude vendor .


## Excluding files from a scan based on the PHP version used

Sometimes a particular file in a project may not comply with the project-wide minimum PHP version, like a file which is conditionally included in the project and contains PHP syntax which needs a higher PHP version to run.

This can make it complicated to run Parallel Lint in a CI context, as the `exclude`s used in the command would have to be adjusted based on the PHP version on which the scan is being run.

PHP Parallel Lint offers a straight-forward way around this, as files can define their own minimum PHP version like so:
```php
<?php // lint >= 7.4

// Code which contains PHP 7.4 syntax.
```

With this comment in place, the file will be automatically skipped when PHP Parallel Lint is run on a PHP version lower than PHP 7.4.

Note: The `// lint >= 7.4` comment has to be only the first line of the file and must directly follow the PHP open tag.


## How to upgrade

Are you using `jakub-onderka/php-parallel-lint` package? You can switch to `php-parallel-lint/php-parallel-lint` using:

    composer remove --dev jakub-onderka/php-parallel-lint
    composer require --dev php-parallel-lint/php-parallel-lint
