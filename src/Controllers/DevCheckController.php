<?php

namespace SilverStripe\EnvironmentCheck\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\EnvironmentCheck\EnvironmentChecker;

/**
 * Class DevCheckController
 *
 * @package environmentcheck
 */
class DevCheckController extends Controller
{
    private static $url_handlers = [
        '$Suite' => 'index',
    ];

    /**
     * @var array
     */
    private static $allowed_actions = [
        'index'
    ];

    /**
     * Permission code to check for access to this controller.
     *
     * @var string
     */
    private static $permission = 'ADMIN';

    /**
     * @param HTTPRequest $request
     *
     * @return EnvironmentChecker
     *
     * @throws HTTPResponse_Exception
     */
    public function index($request)
    {
        $suite = 'check';

        if ($name = $request->param('Suite')) {
            $suite = $name;
        }

        /** @var EnvironmentChecker */
        $checker = EnvironmentChecker::create($suite, 'Environment status')
            ->setRequest($request)
            ->setIncludeDetails(true);

        $checker->init($this->config()->permission);

        return $checker;
    }
}
