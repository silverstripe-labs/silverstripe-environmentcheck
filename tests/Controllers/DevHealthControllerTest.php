<?php

namespace SilverStripe\EnvironmentCheck\Tests\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\EnvironmentCheck\Controllers\DevHealthController;
use SilverStripe\EnvironmentCheck\EnvironmentChecker;

/**
 * Class DevHealthControllerTest
 *
 * @mixin PHPUnit_Framework_TestCase
 *
 * @package environmentcheck
 */
class DevHealthControllerTest extends SapphireTest
{
    /**
     * {@inheritDoc}
     * @var array
     */
    protected $usesDatabase = true;

    public function testIndexCreatesChecker()
    {
        $controller = new DevHealthController();

        $request = new HTTPRequest('GET', 'example.com');

        // we need to fake authenticated access as BasicAuth::requireLogin doesn't like empty
        // permission type strings, which is what health check uses.

        putenv('ENVCHECK_BASICAUTH_USERNAME="foo"');
        putenv('ENVCHECK_BASICAUTH_PASSWORD="bar"');

        $_SERVER['PHP_AUTH_USER'] = 'foo';
        $_SERVER['PHP_AUTH_PW'] = 'bar';

        $this->assertInstanceOf(EnvironmentChecker::class, $controller->index($request));
    }
}
