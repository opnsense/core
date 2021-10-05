# PHP Parallel Lint

[![Downloads this Month](https://img.shields.io/packagist/dm/php-parallel-lint/php-parallel-lint.svg)](https://packagist.org/packages/php-parallel-lint/php-parallel-lint)
[![Build Status](https://github.com/php-parallel-lint/PHP-Parallel-Lint/actions/workflows/test.yml/badge.svg)](https://github.com/php-parallel-lint/PHP-Parallel-Lint/actions/workflows/test.yml)
[![License](https://poser.pugx.org/php-parallel-lint/php-parallel-lint/license.svg)](https://packagist.org/packages/php-parallel-lint/php-parallel-lint)

This application checks syntax of PHP files in parallel.
It can output in plain text, colored text, json and checksyntax formats.
Additionally `blame` can be used to show commits that introduced the breakage.

Running parallel jobs in PHP is inspired by Nette framework tests.

The application is officially supported for use with PHP 5.3 to 8.0.

## Table of contents

1. [Installation](#installation)
2. [Example output](#example-output)
3. [History](#history)
4. [Command line options](#command-line-options)
5. [Recommended excludes for Symfony framework](#recommended-excludes-for-symfony-framework)
6. [Create Phar package](#create-phar-package)
7. [How to upgrade](#how-to-upgrade)

## Installation

Install with `composer` as development dependency:

    composer require --dev php-parallel-lint/php-parallel-lint

Alternatively you can install as a standalone `composer` project:

    composer create-project php-parallel-lint/php-parallel-lint /path/to/folder/php-parallel-lint
    /path/to/folder/php-parallel-lint/parallel-lint # running tool

For colored output, install the suggested package `php-parallel-lint/php-console-highlighter`:

    composer require --dev php-parallel-lint/php-console-highlighter

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

- `-p <php>`        		Specify PHP-CGI executable to run (default: 'php').
- `-s, --short`     		Set short_open_tag to On (default: Off).
- `-a, --asp`       		Set asp_tags to On (default: Off).
- `-e <ext>`        		Check only files with selected extensions separated by comma. (default: php,php3,php4,php5,phtml,phpt)
- `--exclude`       		Exclude a file or directory. If you want exclude multiple items, use multiple exclude parameters.
- `-j <num>`        		Run <num> jobs in parallel (default: 10).
- `--colors`        		Force enable colors in console output.
- `--no-colors`     		Disable colors in console output.
- `--no-progress`   		Disable progress in console output.
- `--checkstyle`    		Output results as Checkstyle XML.
- `--json`          		Output results as JSON string (requires PHP 5.4).
- `--gitlab`          		Output results for the GitLab Code Quality widget (requires PHP 5.4), see more in [Code Quality](https://docs.gitlab.com/ee/user/project/merge_requests/code_quality.html) documentation.
- `--blame`         		Try to show git blame for row with error.
- `--git <git>`     		Path to Git executable to show blame message (default: 'git').
- `--stdin`         		Load files and folder to test from standard input.
- `--ignore-fails`  		Ignore failed tests.
- `--syntax-error-callback` File with syntax error callback for ability to modify error, see more in [example](doc/syntax-error-callback.md)
- `-h, --help`      		Print this help.
- `-V, --version`   		Display this application version.


## Recommended excludes for Symfony framework

To run from the command line:

    vendor/bin/parallel-lint --exclude app --exclude vendor .

## Create Phar package

PHP Parallel Lint supports [Box app](https://box-project.github.io/box2/) for creating Phar package. First, install box app:


    curl -LSs https://box-project.github.io/box2/installer.php | php


then run the build command in parallel lint folder, which creates `parallel-lint.phar` file.


    box build

## How to upgrade

Are you using `jakub-onderka/php-parallel-lint` package? You can switch to `php-parallel-lint/php-parallel-lint` using:

    composer remove --dev jakub-onderka/php-parallel-lint
    composer require --dev php-parallel-lint/php-parallel-lint
