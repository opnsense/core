<?php

namespace JakubOnderka\PhpParallelLint;

class Application
{
    const VERSION = '1.3.1';

    // Return codes
    const SUCCESS = 0,
        WITH_ERRORS = 1,
        FAILED = 254; // Error code 255 is reserved for PHP itself

    /**
     * Run the application
     * @return int Return code
     */
    public function run()
    {
        if (in_array('proc_open', explode(',', ini_get('disable_functions')))) {
            echo "Function 'proc_open' is required, but it is disabled by the 'disable_functions' ini setting.", PHP_EOL;
            return self::FAILED;
        }

        if (in_array('-h', $_SERVER['argv']) || in_array('--help', $_SERVER['argv'])) {
            $this->showUsage();
            return self::SUCCESS;
        }

        if (in_array('-V', $_SERVER['argv']) || in_array('--version', $_SERVER['argv'])) {
            $this->showVersion();
            return self::SUCCESS;
        }

        try {
            $settings = Settings::parseArguments($_SERVER['argv']);
            if ($settings->stdin) {
                $settings->addPaths(Settings::getPathsFromStdIn());
            }
            if (empty($settings->paths)) {
                $this->showUsage();
                return self::FAILED;
            }
            $manager = new Manager;
            $result = $manager->run($settings);
            if ($settings->ignoreFails) {
                return $result->hasSyntaxError() ? self::WITH_ERRORS : self::SUCCESS;
            } else {
                return $result->hasError() ? self::WITH_ERRORS : self::SUCCESS;
            }

        } catch (InvalidArgumentException $e) {
            echo "Invalid option {$e->getArgument()}", PHP_EOL, PHP_EOL;
            $this->showOptions();
            return self::FAILED;

        } catch (Exception $e) {
            if (isset($settings) && $settings->format === Settings::FORMAT_JSON) {
                echo json_encode($e);
            } else {
                echo $e->getMessage(), PHP_EOL;
            }
            return self::FAILED;

        } catch (\Exception $e) {
            echo $e->getMessage(), PHP_EOL;
            return self::FAILED;
        }
    }

    /**
     * Outputs the options
     */
    private function showOptions()
    {
        echo <<<HELP
Options:
    -p <php>                Specify PHP-CGI executable to run (default: 'php').
    -s, --short             Set short_open_tag to On (default: Off).
    -a, -asp                Set asp_tags to On (default: Off).
    -e <ext>                Check only files with selected extensions separated by comma.
                            (default: php,php3,php4,php5,phtml,phpt)
    --exclude               Exclude a file or directory. If you want exclude multiple items,
                            use multiple exclude parameters.
    -j <num>                Run <num> jobs in parallel (default: 10).
    --colors                Enable colors in console output. (disables auto detection of color support)
    --no-colors             Disable colors in console output.
    --no-progress           Disable progress in console output.
    --json                  Output results as JSON string.
    --gitlab                Output results for the GitLab Code Quality Widget.
    --checkstyle            Output results as Checkstyle XML.
    --blame                 Try to show git blame for row with error.
    --git <git>             Path to Git executable to show blame message (default: 'git').
    --stdin                 Load files and folder to test from standard input.
    --ignore-fails          Ignore failed tests.
    --syntax-error-callback File with syntax error callback for ability to modify error
    -h, --help              Print this help.
    -V, --version           Display this application version

HELP;
    }

    /**
     * Outputs the current version
     */
    private function showVersion()
    {
        echo 'PHP Parallel Lint version ' . self::VERSION.PHP_EOL;
    }

    /**
     * Shows usage
     */
    private function showUsage()
    {
        $this->showVersion();
        echo <<<USAGE
-------------------------------
Usage:
parallel-lint [sa] [-p php] [-e ext] [-j num] [--exclude dir] [files or directories]

USAGE;
        $this->showOptions();
    }
}
