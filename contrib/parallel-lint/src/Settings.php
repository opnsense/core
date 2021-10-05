<?php
namespace JakubOnderka\PhpParallelLint;

class Settings
{

    /**
     * constants for enum settings
     */
    const FORCED = 'FORCED';
    const DISABLED = 'DISABLED';
    const AUTODETECT = 'AUTODETECT';

    const FORMAT_TEXT = 'text';
    const FORMAT_JSON = 'json';
    const FORMAT_GITLAB = 'gitlab';
    const FORMAT_CHECKSTYLE = 'checkstyle';

    /**
     * Path to PHP executable
     * @var string
     */
    public $phpExecutable = 'php';

    /**
     * Check code inside PHP opening short tag <? or <?= in PHP 5.3
     * @var bool
     */
    public $shortTag = false;

    /**
     * Check PHP code inside ASP-style <% %> tags.
     * @var bool
     */
    public $aspTags = false;

    /**
     * Number of jobs running in same time
     * @var int
     */
    public $parallelJobs = 10;

    /**
     * If path contains directory, only file with these extensions are checked
     * @var array
     */
    public $extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'phpt');

    /**
     * Array of file or directories to check
     * @var array
     */
    public $paths = array();

    /**
     * Don't check files or directories
     * @var array
     */
    public $excluded = array();

    /**
     * Mode for color detection. Possible values: self::FORCED, self::DISABLED and self::AUTODETECT
     * @var string
     */
    public $colors = self::AUTODETECT;

    /**
     * Show progress in text output
     * @var bool
     */
    public $showProgress = true;

    /**
     * Output format (see FORMAT_* constants)
     * @var string
     */
    public $format = self::FORMAT_TEXT;

    /**
     * Read files and folder to tests from standard input (blocking)
     * @var bool
     */
    public $stdin = false;

    /**
     * Try to show git blame for row with error
     * @var bool
     */
    public $blame = false;

    /**
     * Path to git executable for blame
     * @var string
     */
    public $gitExecutable = 'git';

    /**
     * @var bool
     */
    public $ignoreFails = false;

    /**
     * @var bool
     */
    public $showDeprecated = false;

    /**
     * Path to a file with syntax error callback
     * @var string|null
     */
    public $syntaxErrorCallbackFile = null;

    /**
     * @param array $paths
     */
    public function addPaths(array $paths)
    {
        $this->paths = array_merge($this->paths, $paths);
    }

    /**
     * @param array $arguments
     * @return Settings
     * @throws InvalidArgumentException
     */
    public static function parseArguments(array $arguments)
    {
        $arguments = new ArrayIterator(array_slice($arguments, 1));
        $settings = new self;

        // Use the currently invoked php as the default if possible
        if (defined('PHP_BINARY')) {
            $settings->phpExecutable = PHP_BINARY;
        }

        foreach ($arguments as $argument) {
            if ($argument[0] !== '-') {
                $settings->paths[] = $argument;
            } else {
                switch ($argument) {
                    case '-p':
                        $settings->phpExecutable = $arguments->getNext();
                        break;

                    case '-s':
                    case '--short':
                        $settings->shortTag = true;
                        break;

                    case '-a':
                    case '--asp':
                        $settings->aspTags = true;
                        break;

                    case '--exclude':
                        $settings->excluded[] = $arguments->getNext();
                        break;

                    case '-e':
                        $settings->extensions = array_map('trim', explode(',', $arguments->getNext()));
                        break;

                    case '-j':
                        $settings->parallelJobs = max((int) $arguments->getNext(), 1);
                        break;

                    case '--colors':
                        $settings->colors = self::FORCED;
                        break;

                    case '--no-colors':
                        $settings->colors = self::DISABLED;
                        break;

                    case '--no-progress':
                        $settings->showProgress = false;
                        break;

                    case '--json':
                        $settings->format = self::FORMAT_JSON;
                        break;

                    case '--gitlab':
                        $settings->format = self::FORMAT_GITLAB;
                        break;

                    case '--checkstyle':
                        $settings->format = self::FORMAT_CHECKSTYLE;
                        break;

                    case '--git':
                        $settings->gitExecutable = $arguments->getNext();
                        break;

                    case '--stdin':
                        $settings->stdin = true;
                        break;

                    case '--blame':
                        $settings->blame = true;
                        break;

                    case '--ignore-fails':
                        $settings->ignoreFails = true;
                        break;

                    case '--show-deprecated':
                        $settings->showDeprecated = true;
                        break;

                    case '--syntax-error-callback':
                        $settings->syntaxErrorCallbackFile = $arguments->getNext();
                        break;

                    default:
                        throw new InvalidArgumentException($argument);
                }
            }
        }

        return $settings;
    }

    /**
     * @return array
     */
    public static function getPathsFromStdIn()
    {
        $content = stream_get_contents(STDIN);

        if (empty($content)) {
            return array();
        }

        $lines = explode("\n", rtrim($content));
        return array_map('rtrim', $lines);
    }
}

class ArrayIterator extends \ArrayIterator
{
    public function getNext()
    {
        $this->next();
        return $this->current();
    }
}
