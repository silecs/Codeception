<?php
namespace Codeception\Module;

use Codeception\Lib\Framework;
use Codeception\Exception\ModuleConfigException;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\TestInterface;
use Codeception\Module\Yii1\TransactionTrait;
use Codeception\Lib\Connector\Yii1 as Yii1Connector;
use Codeception\Util\ReflectionHelper;
use Yii;

/**
 * This module provides integration with [Yii Framework 1.1](http://www.yiiframework.com/doc/guide/).
 *
 * The following configurations are available for this module:
 *
 *  * `appPath` - full path to the application, include index.php</li>
 *  * `url` - full url to the index.php entry script</li>
 *
 * In your index.php you must return an array with correct configuration for the application:
 *
 * For the simple created yii application index.php will be like this:
 *
 * ```php
 * <?php
 * // change the following paths if necessary
 * $yii=dirname(__FILE__).'/../yii/framework/yii.php';
 * $config=dirname(__FILE__).'/protected/config/main.php';
 *
 * // remove the following lines when in production mode
 * defined('YII_DEBUG') or define('YII_DEBUG',true);
 * // specify how many levels of call stack should be shown in each log message
 * defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);
 * require_once($yii);
 * return array(
 *        'class' => 'CWebApplication',
 *        'config' => $config,
 * );
 * ```
 *
 * You can use this module by setting params in your `functional.suite.yml`:
 *
 * ```yaml
 * actor: FunctionalTester
 * modules:
 *     enabled:
 *         - Yii1:
 *             appPath: '/path/to/index.php'
 *             url: 'http://localhost/path/to/index.php'
 *         - \Helper\Functional
 * ```
 *
 * You will also need to install [Codeception-Yii Bridge](https://github.com/Codeception/YiiBridge)
 * which include component wrappers for testing.
 *
 * When you are done, you can test this module by creating new empty Yii application and creating this Cept scenario:
 *
 * ```
 * php codecept.phar g:cept functional IndexCept
 * ```
 *
 * and write it as in example:
 *
 * ```php
 * <?php
 * $I = new FunctionalTester($scenario);
 * $I->wantTo('Test index page');
 * $I->amOnPage('/index.php');
 * $I->see('My Web Application','#header #logo');
 * $I->click('Login');
 * $I->see('Login','h1');
 * $I->see('Username');
 * $I->fillField('#LoginForm_username','demo');
 * $I->fillField('#LoginForm_password','demo');
 * $I->click('#login-form input[type="submit"]');
 * $I->seeLink('Logout (demo)');
 * $I->click('Logout (demo)');
 * $I->seeLink('Login');
 * ```
 *
 * Then run codeception: php codecept.phar --steps run functional
 * You must see "OK" and that all steps are marked with asterisk (*).
 * Do not forget that after adding module in your functional.suite.yml you must run codeception "build" command.
 *
 * ### Public Properties
 *
 * `client`: instance of `\Codeception\Lib\Connector\Yii1`
 *
 * ### Parts
 *
 * If you ever encounter error message:
 *
 * ```
 * Yii1 module conflicts with WebDriver
 * ```
 *
 * you should include Yii module partially, with `init` part only
 *
 * * `init`: only initializes module and not provides any actions from it. Can be used for unit/acceptance tests to avoid conflicts.
 *
 * ### Acceptance Testing Example:
 *
 * In `acceptance.suite.yml`:
 *
 * ```yaml
 * class_name: AcceptanceTester
 * modules:
 *     enabled:
 *         - WebDriver:
 *             browser: firefox
 *             url: http://localhost
 *         - Yii1:
 *             appPath: '/path/to/index.php'
 *             url: 'http://localhost/path/to/index.php'
 *             part: init # to not conflict with WebDriver
 *         - \Helper\Acceptance
 * ```
 */
class Yii1 extends Framework implements PartedModule
{
    use TransactionTrait;

    protected $config = [
        'appPath' => '',
        'transaction' => true,
        'url' => '',
    ];

    /**
     * Application path and url must be set always
     * @var array
     */
    protected $requiredFields = ['appPath', 'url'];

    /**
     * Application settings array('class'=>'YourAppClass','config'=>'YourAppArrayConfig');
     * @var array
     */
    private $appSettings;

    private $_appConfig;

    /**
     * @var array The contents of $_SERVER upon initialization of this object.
     * This is only used to restore it upon object destruction.
     * It MUST not be used anywhere else.
     */
    private $server;

    public function _initialize()
    {
        $this->readConfigFiles();
        $this->defineConstants();
        $this->server = $_SERVER;
        $_SERVER = array_merge($_SERVER, $this->getServerGlobal());
        if (!function_exists('launch_codeception_yii_bridge')) {
            throw new ModuleConfigException(
                __CLASS__,
                "Codeception-Yii Bridge is not launched. In order to run tests you need to install "
                . "https://github.com/Codeception/YiiBridge Implement function 'launch_codeception_yii_bridge' to "
                . "load all Codeception overrides"
            );
        }
        $this->createYiiApp();
    }

    /**
     * Fill in $this->appSettings and $this->_appConfig from the files
     * configured by 'appPath' and 'config', respectively.
     */
    private function readConfigFiles(): void
    {
        if (!file_exists($this->config['appPath'])) {
            throw new ModuleConfigException(
                __CLASS__,
                "Couldn't load application config file {$this->config['appPath']}\n" .
                "Please provide application bootstrap file configured for testing"
            );
        }
        $this->appSettings = include($this->config['appPath']); //get application settings in the entry script

        // get configuration from array or file
        if (is_array($this->appSettings['config'])) {
            $this->_appConfig = $this->appSettings['config'];
        } else {
            if (!file_exists($this->appSettings['config'])) {
                throw new ModuleConfigException(
                    __CLASS__,
                    "Couldn't load configuration file from Yii app file: {$this->appSettings['config']}\n" .
                    "Please provide valid 'config' parameter"
                );
            }
            $this->_appConfig = include($this->appSettings['config']);
        }
    }

    private function defineConstants(): void
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'test');
        defined('YII_ENABLE_EXCEPTION_HANDLER') or define('YII_ENABLE_EXCEPTION_HANDLER', false);
        defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', false);
    }

    private function getServerGlobal(): array
    {
        $entryUrl = $this->config['url'];
        return [
            'SCRIPT_FILENAME' => $this->config['appPath'], // server path, e.g. "/srv/www/index-test.php"
            'SCRIPT_NAME' => parse_url($entryUrl, PHP_URL_PATH), // web path, e.g. "/index-test.php"
            'SERVER_NAME' => parse_url($entryUrl, PHP_URL_HOST),
            'SERVER_PORT' => parse_url($entryUrl, PHP_URL_PORT) ?: '80',
            'HTTPS' => parse_url($entryUrl, PHP_URL_SCHEME) === 'https',
        ];
    }

    private function createYiiApp()
    {
        launch_codeception_yii_bridge();
        Yii::$enableIncludePath = false;
        if ($this->client !== null) {
            $this->client->resetApplication();
        }
        Yii::createApplication($this->appSettings['class'], $this->_appConfig);
    }

    /*
     * Create the client connector. Called before each test
     */
    public function _createClient()
    {
        $this->client = new Yii1Connector($this->getServerGlobal());
        $this->client->appPath = $this->config['appPath'];
        $this->client->url = $this->config['url'];
        $this->client->appSettings = [
            'class'  => $this->appSettings['class'],
            'config' => $this->_appConfig,
        ];
    }

    public function _before(TestInterface $test)
    {
        $this->_createClient();
        $this->createYiiApp();
        if ($this->config['transaction']) {
            $this->startTransaction();
        }
    }

    public function _after(TestInterface $test)
    {
        $_SESSION = [];
        $_FILES = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_SERVER = array_merge($this->server, $this->getServerGlobal());
        if ($this->client !== null) {
            if ($this->config['transaction']) {
                $this->rollbackTransaction();
            }
            $this->client->resetApplication();
        }
        parent::_after($test);
    }

    /**
     * Getting domain regex from rule template and parameters
     *
     * @param string $template
     * @param array $parameters
     * @return string
     */
    private function getDomainRegex($template, $parameters = [])
    {
        $host = parse_url($template, PHP_URL_HOST);
        if ($host) {
            $template = $host;
        }
        if (strpos($template, '<') !== false) {
            $template = str_replace(['<', '>'], '#', $template);
        }
        $template = preg_quote($template);
        foreach ($parameters as $name => $value) {
            $template = str_replace("#$name#", $value, $template);
        }
        return '/^' . $template . '$/u';
    }


    /**
     * Returns a list of regex patterns for recognized domain names
     *
     * @return array
     */
    public function getInternalDomains()
    {
        $domains = [$this->getDomainRegex(Yii::app()->request->getHostInfo())];
        if (Yii::app()->urlManager->urlFormat === 'path') {
            $parent = Yii::app()->urlManager instanceof \CUrlManager ? '\CUrlManager' : null;
            $rules = ReflectionHelper::readPrivateProperty(Yii::app()->urlManager, '_rules', $parent);
            foreach ($rules as $rule) {
                if ($rule->hasHostInfo === true) {
                    $domains[] = $this->getDomainRegex($rule->template, $rule->params);
                }
            }
        }
        return array_unique($domains);
    }

    public function _parts()
    {
        return ['init', 'initialize'];
    }
}
