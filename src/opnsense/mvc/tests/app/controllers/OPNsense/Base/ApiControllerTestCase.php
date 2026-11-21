<?php

namespace tests\OPNsense\Base;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/TestSession.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Mvc\Request;
use OPNsense\Mvc\Response;
use OPNsense\Mvc\Security;

/**
 * Base class for API controller tests.
 *
 * Each test starts from a copy of <TestName>/config.xml next to the test class, so saves don't touch the
 * fixture, and runs with simulate_mode set, so calls to configd return nothing. simulate_mode only applies
 * without a configd socket, so on a system running configd these tests are skipped rather than have them
 * trigger real configd actions.
 *
 * Actions are called directly, after initialize(), as the dispatcher does once authentication has passed
 * (beforeExecuteRoute() then has set the session user as the logged in user). Page ACLs, CSRF checks, the
 * session language and JSON request bodies are not exercised.
 */
abstract class ApiControllerTestCase extends \PHPUnit\Framework\TestCase
{
    private const CONFIGD_SOCKET = '/var/run/configd.socket';

    private string $workDir;
    private string $previousConfigDir;
    private $previousSimulateMode;

    protected function setUp(): void
    {
        if (file_exists(self::CONFIGD_SOCKET)) {
            $this->markTestSkipped('configd is running, its actions would not be simulated');
        }
        $fixture = dirname((new \ReflectionClass($this))->getFileName()) . '/' .
            (new \ReflectionClass($this))->getShortName() . '/config.xml';
        $this->workDir = sys_get_temp_dir() . '/opnsense-test-' . bin2hex(random_bytes(6));
        mkdir($this->workDir . '/backup', 0700, true);
        copy($fixture, $this->workDir . '/config.xml');
        $appconfig = new AppConfig();
        $this->previousConfigDir = (string)$appconfig->application->configDir;
        $this->previousSimulateMode = $appconfig->globals->simulate_mode;
        $appconfig->update('application.configDir', $this->workDir);
        $appconfig->update('globals.simulate_mode', true);
        Config::getInstance()->forceReload();
    }

    protected function tearDown(): void
    {
        if (!isset($this->workDir)) {
            return;
        }
        $appconfig = new AppConfig();
        $appconfig->update('application.configDir', $this->previousConfigDir);
        $appconfig->update('globals.simulate_mode', $this->previousSimulateMode);
        $_POST = $_GET = $_REQUEST = $_SESSION = [];
        unset($_SERVER['REQUEST_METHOD']);
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->workDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            ) as $item
        ) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->workDir);
    }

    /**
     * call a controller action as an authenticated user
     * @param string $class controller class
     * @param string $action action method, for example delAction
     * @param array $params action parameters (the uri parts after the action)
     * @param array $post posted fields
     * @param string $method request method
     * @param string $user user name in the session
     * @return mixed action result
     */
    protected function callAction(
        string $class,
        string $action,
        array $params = [],
        array $post = [],
        string $method = 'POST',
        string $user = 'root'
    ) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $_REQUEST = $post;
        $_GET = [];
        /* Config::save() records the session user in the revision */
        $_SESSION = ['Username' => $user];
        $controller = new $class();
        $controller->session = new TestSession(['Username' => $user]);
        $controller->request = new Request();
        $controller->response = new Response();
        $controller->security = new Security($controller->session, $controller->request);
        (new \ReflectionProperty($controller, 'logged_in_user'))->setValue($controller, $user);
        $controller->initialize();
        return $controller->$action(...$params);
    }

    /**
     * @return \SimpleXMLElement the configuration as saved
     */
    protected function savedConfig()
    {
        Config::getInstance()->forceReload();
        return Config::getInstance()->object();
    }
}
