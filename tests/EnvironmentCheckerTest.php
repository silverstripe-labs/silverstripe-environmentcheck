<?php

namespace SilverStripe\EnvironmentCheck\Tests;

use ReflectionProperty;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\EnvironmentCheck\EnvironmentChecker;
use SilverStripe\EnvironmentCheck\EnvironmentCheckSuite;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Kernel;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\HTTPRequest;

/**
 * Class EnvironmentCheckerTest
 *
 * @package environmentcheck
 */
class EnvironmentCheckerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function tearDown(): void
    {
        EnvironmentCheckSuite::reset();
        parent::tearDown();
    }

    public function testOnlyLogsWithErrors()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_warning', true);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', true);

        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest\CheckNoErrors());

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();

        $logger->expects($this->never())->method('log');

        Injector::inst()->registerService($logger, LoggerInterface::class);

        (new EnvironmentChecker('test suite', 'test'))->index();
    }

    public function testLogsWithWarnings()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_warning', true);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', false);

        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest\CheckWarnings());
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest\CheckErrors());

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();

        $logger->expects($this->once())
            ->method('log')
            ->withConsecutive(
                [$this->equalTo(LogLevel::WARNING)],
                [$this->anything()]
            );

        Injector::inst()->registerService($logger, LoggerInterface::class);

        (new EnvironmentChecker('test suite', 'test'))->index();
    }

    public function testLogsWithErrors()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', false);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', true);

        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest\CheckWarnings());
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest\CheckErrors());

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();

        $logger->expects($this->once())
            ->method('log')
            ->withConsecutive(
                [$this->equalTo(LogLevel::ALERT), $this->anything()],
                [$this->equalTo(LogLevel::WARNING), $this->anything()]
            );

        Injector::inst()->registerService($logger, LoggerInterface::class);

        (new EnvironmentChecker('test suite', 'test'))->index();
    }

    public static function provideBasicAuthAdminBypass(): array
    {
        return [
            'logged-out-valid-creds' => [
                'user' => 'logged-out',
                'validCreds' => true,
                'expectedSuccess' => true,
            ],
            'logged-out-invalid-creds' => [
                'user' => 'logged-out',
                'validCreds' => false,
                'expectedSuccess' => false,
            ],
            'logged-out-no-creds' => [
                'user' => 'logged-out',
                'validCreds' => null,
                'expectedSuccess' => false,
            ],
            'non-admin-valid-creds' => [
                'user' => 'non-admin',
                'validCreds' => true,
                'expectedSuccess' => true,
            ],
            'non-admin-invalid-creds' => [
                'user' => 'non-admin',
                'validCreds' => false,
                'expectedSuccess' => false,
            ],
            'non-admin-no-creds' => [
                'user' => 'non-admin',
                'validCreds' => null,
                'expectedSuccess' => false,
            ],
            'admin-valid-creds' => [
                'user' => 'admin',
                'validCreds' => true,
                'expectedSuccess' => true,
            ],
            'admin-invalid-creds' => [
                'user' => 'admin',
                'validCreds' => false,
                'expectedSuccess' => true,
            ],
            'admin-no-creds' => [
                'user' => 'admin',
                'validCreds' => null,
                'expectedSuccess' => true,
            ],
        ];
    }

    /**
     * @dataProvider provideBasicAuthAdminBypass
     */
    public function testBasicAuthAdminBypass(
        string $user,
        ?bool $validCreds,
        bool $expectedSuccess,
    ): void {
        $this->setupBasicAuthEnv(true, true);
        // Log in or out
        if ($user === 'admin') {
            $this->logInWithPermission('ADMIN');
        } elseif ($user === 'non-admin') {
            $this->logInWithPermission('NOT_AN_ADMIN');
        } else {
            $this->logOut();
        }
        $request = new HTTPRequest('GET', 'dev/check');
        if ($validCreds !== null) {
            // Simulate passing in basic auth creds
            $request->addHeader('PHP_AUTH_USER', 'test');
            $request->addHeader('PHP_AUTH_PW', $validCreds ? 'password' : 'invalid');
        }
        // Run test
        $checker = new EnvironmentChecker('test suite', 'test');
        $checker->setRequest($request);
        $success = null;
        try {
            $checker->init();
            $success = true;
        } catch (HTTPResponse_Exception $e) {
            $success = false;
            $response = $e->getResponse();
            $this->assertEquals(401, $response->getStatusCode());
        }
        $this->assertSame($expectedSuccess, $success);
    }

    public static function provideBasicAuthEnvVariables(): array
    {
        return [
            'logged-in-env-vars-set' => [
                'loggedIn' => true,
                'setUsernameEnvVar' => true,
                'setPwEnvVar' => true,
                'expected' => true,
            ],
            'logged-in-env-no-username' => [
                'loggedIn' => true,
                'setUsernameEnvVar' => false,
                'setPwEnvVar' => true,
                'expected' => true,
            ],
            'logged-in-env-no-password' => [
                'loggedIn' => true,
                'setUsernameEnvVar' => true,
                'setPwEnvVar' => false,
                'expected' => true,
            ],
            'logged-in-env-no-username-or-pw' => [
                'loggedIn' => true,
                'setUsernameEnvVar' => false,
                'setPwEnvVar' => false,
                'expected' => true,
            ],
            'logged-out-env-vars-set' => [
                'loggedIn' => false,
                'setUsernameEnvVar' => true,
                'setPwEnvVar' => true,
                'expected' => true,
            ],
            'logged-out-env-no-username' => [
                'loggedIn' => false,
                'setUsernameEnvVar' => false,
                'setPwEnvVar' => true,
                'expected' => false,
            ],
            'logged-out-env-no-password' => [
                'loggedIn' => false,
                'setUsernameEnvVar' => true,
                'setPwEnvVar' => false,
                'expected' => false,
            ],
            'logged-out-env-no-username-or-pw' => [
                'loggedIn' => false,
                'setUsernameEnvVar' => false,
                'setPwEnvVar' => false,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider provideBasicAuthEnvVariables
     */
    public function testBasicAuthEnvVariables(
        bool $loggedIn,
        bool $setUsernameEnvVar,
        bool $setPwEnvVar,
        bool $expected,
    ): void {
        $this->setupBasicAuthEnv($setUsernameEnvVar, $setPwEnvVar);
        if ($loggedIn) {
            $this->logInWithPermission('ADMIN');
        } else {
            $this->logOut();
        }
        $request = new HTTPRequest('GET', 'dev/check');
        $request->addHeader('PHP_AUTH_USER', 'test');
        $request->addHeader('PHP_AUTH_PW', 'password');
        // Run test
        $checker = new EnvironmentChecker('test suite', 'test');
        $checker->setRequest($request);
        $success = null;
        try {
            $checker->init();
            $success = true;
        } catch (HTTPResponse_Exception $e) {
            $success = false;
            $response = $e->getResponse();
            $this->assertEquals(302, $response->getStatusCode());
        }
        $this->assertSame($expected, $success);
    }

    /**
     * Setup the test environment so that we can use basic auth
     */
    private function setupBasicAuthEnv(bool $setUsernameEnvVar, bool $setPwEnvVar): void
    {
        // Pretend we're not using CLI which will bypass basic auth
        $reflectionCli = new ReflectionProperty(Environment::class, 'isCliOverride');
        $reflectionCli->setAccessible(true);
        $reflectionCli->setValue(false);
        // Change from dev to test mode as dev mode will bypass basic auth
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('test');
        // Set the basic auth env vars
        Environment::setEnv('ENVCHECK_BASICAUTH_USERNAME', $setUsernameEnvVar ? 'test' : null);
        Environment::setEnv('ENVCHECK_BASICAUTH_PASSWORD', $setPwEnvVar ? 'password' : null);
    }
}
