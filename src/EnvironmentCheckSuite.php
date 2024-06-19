<?php

namespace SilverStripe\EnvironmentCheck;

use Exception;
use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Represents a suite of environment checks.
 * Specific checks can be registered against a named instance of EnvironmentCheckSuite.
 *
 * Usage #1 - _config.php
 * EnvironmentCheckSuite::register('health', 'MyHealthCheck("my param")', 'Title of my health check');
 *
 * Usage #2 - config.yml
 * EnvironmentCheckSuite:
 *   registered_checks:
 *     mycheck:
 *       definition: 'MyHealthCheck("my param")'
 *       title: 'Title of my health check'
 *   registered_suites:
 *     health:
 *       - mycheck
 *
 * $result = EnvironmentCheckSuite::inst('health')->run();
 *
 * @package environmentcheck
 */
class EnvironmentCheckSuite
{
    use Configurable;
    use Injectable;
    use Extensible;
    /**
     * Name of this suite.
     *
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $checks = [];

    /**
     * Associative array of named checks registered via the config system. Each check should specify:
     * - definition (e.g. 'MyHealthCheck("my param")')
     * - title (e.g. 'Is my feature working?')
     * - state (setting this to 'disabled' will cause suites to skip this check entirely.
     *
     * @var array
     */
    private static $registered_checks = [];

    /**
     * Associative array of named suites registered via the config system. Each suite should enumerate
     * named checks that have been configured in 'registered_checks'.
     *
     * @var array
     */
    private static $registered_suites = [];

    /**
     * Load checks for this suite from the configuration system. This is an alternative to the
     * EnvironmentCheckSuite::register - both can be used, checks will be appended to the suite.
     *
     * @param string $suiteName The name of this suite.
     */
    public function __construct($suiteName)
    {
        if (empty($this->config()->registered_suites[$suiteName])) {
            // Not registered via config system, but it still may be configured later
            // via EnvironmentCheckSuite::register.
            return;
        }

        foreach ($this->config()->registered_suites[$suiteName] as $checkName) {
            if (empty($this->config()->registered_checks[$checkName])) {
                throw new InvalidArgumentException(
                    "Bad EnvironmentCheck: '$checkName' - the named check has not been registered."
                );
            }

            $check = $this->config()->registered_checks[$checkName];

            // Existing named checks can be disabled by setting their 'state' to 'disabled'.
            // This is handy for disabling checks mandated by modules.
            if (!empty($check['state']) && $check['state'] === 'disabled') {
                continue;
            }

            // Add the check to this suite.
            $this->push($check['definition'], $check['title']);
        }
    }

    /**
     * Run this test suite and return the result code of the worst result.
     *
     * @return EnvironmentCheckSuiteResult
     */
    public function run()
    {
        $result = new EnvironmentCheckSuiteResult();

        foreach ($this->checkInstances() as $check) {
            list($checkClass, $checkTitle) = $check;
            try {
                list($status, $message) = $checkClass->check();
            // If the check fails, register that as an error
            } catch (Exception $e) {
                $status = EnvironmentCheck::ERROR;
                $message = $e->getMessage();
            }
            $result->addResult($status, $message, $checkTitle);
        }

        return $result;
    }

    /**
     * Get instances of all the environment checks.
     *
     * @return EnvironmentChecker[]
     * @throws InvalidArgumentException
     */
    protected function checkInstances()
    {
        $output = [];
        foreach ($this->checks as $check) {
            list($checkClass, $checkTitle) = $check;
            if (is_string($checkClass)) {
                $checkInst = Injector::inst()->create($checkClass);
                if ($checkInst instanceof EnvironmentCheck) {
                    $output[] = [$checkInst, $checkTitle];
                } else {
                    throw new InvalidArgumentException(
                        "Bad EnvironmentCheck: '$checkClass' - the named class doesn't implement EnvironmentCheck"
                    );
                }
            } elseif ($checkClass instanceof EnvironmentCheck) {
                $output[] = [$checkClass, $checkTitle];
            } else {
                throw new InvalidArgumentException("Bad EnvironmentCheck: " . var_export($check, true));
            }
        }
        return $output;
    }

    /**
     * Add a check to this suite.
     *
     * @param mixed $check
     * @param string $title
     */
    public function push($check, $title = null)
    {
        if (!$title) {
            $title = is_string($check) ? $check : get_class($check);
        }
        $this->checks[] = [$check, $title];
    }

    /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * Return a named instance of EnvironmentCheckSuite.
     *
     * @param string $name
     *
     * @return EnvironmentCheckSuite
     */
    public static function inst($name)
    {
        if (!isset(EnvironmentCheckSuite::$instances[$name])) {
            EnvironmentCheckSuite::$instances[$name] = new EnvironmentCheckSuite($name);
        }
        return EnvironmentCheckSuite::$instances[$name];
    }

    /**
     * Register a check against the named check suite.
     *
     * @param string|array $names
     * @param EnvironmentCheck $check
     * @param string|array
     */
    public static function register($names, $check, $title = null)
    {
        if (!is_array($names)) {
            $names = [$names];
        }

        foreach ($names as $name) {
            EnvironmentCheckSuite::inst($name)->push($check, $title);
        }
    }

    /**
     * Unregisters all checks.
     */
    public static function reset()
    {
        EnvironmentCheckSuite::$instances = [];
    }
}
