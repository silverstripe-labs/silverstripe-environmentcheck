# SilverStripe Environment Checker Module

[![Build Status](http://img.shields.io/travis/silverstripe-labs/silverstripe-environmentalcheck.svg?style=flat-square)](https://travis-ci.org/silverstripe-labs/silverstripe-environmentalcheck)
[![Code Quality](http://img.shields.io/scrutinizer/g/silverstripe-labs/silverstripe-environmentalcheck.svg?style=flat-square)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-environmentalcheck)
[![Code Coverage](http://img.shields.io/scrutinizer/coverage/g/silverstripe-labs/silverstripe-environmentalcheck.svg?style=flat-square)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-environmentalcheck)
[![Version](http://img.shields.io/packagist/v/silverstripe/environmentalcheck.svg?style=flat-square)](https://packagist.org/packages/silverstripe/silverstripe-environmentalcheck)
[![License](http://img.shields.io/packagist/l/silverstripe/environmentalcheck.svg?style=flat-square)](LICENSE.md)

This module adds an API for running environment checks to your API.

 * `dev/health` - A public URL that performs a quick check that this environment is functioning.  This could be tied to
 a load balancer, for example.
 * `dev/check` - An admin-only URL that performs a more comprehensive set of checks.  This could be tied to a deployment
 system, for example.
 * `dev/check/<suite>` - Check a specific suite (admin only)

## Aren't these just unit tests?

Almost, but not really. Environment checks differ from unit tests in two important ways:

 * **They test environment specific settings.** Unit tests are designed to use dummy data and mock interfaces to
 external system.  Environment checks check the real systems and data that the given environment is actually connected
 to.
 * **They can't modify data.** Because these checks will run using production databases, they can't go modifying the
 data in there. This is the biggest reason why we haven't used the same base class as a unit test for writing
 environment checks - we wanted to make it impossible to accidentally plug a unit test into the environment checker!

## Installation

```sh
$ composer require silverstripe/environmentcheck
```

You'll also need to run `/dev/build`.

## Registering Checks

There are two ways to register your checks, both can be used at the same time. The checks will be appended to the suite.
>>>>>>> Allow /dev/health to be public:README.md

### Activating Directly

Register checks in your own `_config.php` - see the `_config.php` in this
module for some defaults.

```php
EnvironmentCheckSuite::register('health', 'DatabaseCheck', "Can we connect to the database?");
EnvironmentCheckSuite::register('check', 'URLCheck("")', "Is the homepage accessible?");
```

### Activating Via Config

Register your checks on the `EnvironmentCheckSuite`. The same named check may be used multiple times.

```yaml
EnvironmentCheckSuite:
  registered_checks:
    db:
      definition: 'DatabaseCheck("Page")'
      title: 'Is the database accessible?'
    url:
      definition: 'URLCheck()'
      title: 'Is the homepage accessible?'
  registered_suites:
    check:
      - db
    health:
      - db
      - url
```

You can also disable checks configured this way. This is handy if you want to override a check imposed on your project
by some other module. Just set the "state" property of the check to "disabled" like this:

```yaml
EnvironmentCheckSuite:
  registered_checks:
    db:
      state: disabled
```

## Available checks

 * `DatabaseCheck`: Check that the connection to the database is working, by ensuring that the table exists and that
 the table contain some records.
 * `URLCheck`: Check that a given URL is functioning, by default, the homepage.
 * `HasFunctionCheck`: Check that the given function exists. This can be used to check that PHP modules or features
 are installed.
 * `HasClassCheck`: Check that the given class exists. This can be used to check that PHP modules or features are
 installed.
 * `FileWriteableCheck`: Check that the given file is writable.
 * `FileAgeCheck`: Checks for the maximum age of one or more files or folders. Useful for files which should be
 frequently auto-generated, like static caches, as well as for backup files and folders.
 * `ExternalURLCheck`: Checks that one or more URLs are reachable via HTTP.
 * `SMTPConnectCheck`: Checks if the SMTP connection configured through PHP.ini works as expected.
 * `SolrIndexCheck`: Checks if the Solr cores of given class are available.
 * `BinaryExistsCheck`: Checks if a binary is available on the server

## Authentication

`dev/health` is public and requires no authentication.

By default, accessing the `dev/check` URL will not require authentication on CLI and dev environments, but if you're
trying to access it on a live or test environment, it will respond with a 403 HTTP status unless you're logged in as
an administrator on the site.

You may wish to have an automated service check `dev/check` periodically, but not want to open it up for public access.
You can enable basic authentication by defining the following in your environment:

	define('ENVCHECK_BASICAUTH_USERNAME', 'test');
	define('ENVCHECK_BASICAUTH_PASSWORD', 'password');

Now if you access `dev/check` in a browser it will pop up a basic auth popup, and if the submitted username and password
match the ones defined the username and password defined in the environment, access will be granted to the page.

## Adding more checks

To add more checks, you should put additional `EnvironmentCheckSuite::register` calls into your `_config.php`.  See the `_config.php` file of this module for examples.

	:::php
	EnvironmentCheckSuite::register('check', 'HasFunctionCheck("curl_init")', "Does PHP have CURL support?");
	EnvironmentCheckSuite::register('check', 'HasFunctionCheck("imagecreatetruecolor")', "Does PHP have GD2 support?");
	
The first argument is the name of the check suite.  There are two built-in check suites, "health", and "check",
corresponding to the `dev/health` and `dev/check` URLs.  If you wish, you can create your own check suites and execute
them on other URLs. You can also add a check to more than one suite by passing the first argument as an array.

The module comes bundled with a few checks in `DefaultHealthChecks.php`.  However, to test your own application, you
probably want to write custom checks.

 * Implement the `EnvironmentCheck` interface
 * Define the `check()` function, which returns a 2 element array:
   * The first element is one of `EnvironmentCheck::OK`, `EnvironmentCheck::WARNING`, `EnvironmentCheck::ERROR`,
   depending on the status of the check.
   * The second element is a string describing the response.

Here is a simple example of how you might create a check to test your own code.  In this example, we are checking that
an instance of the `MyGateway` class will return "foo" when `call()` is called on it.  Testing interfaces with 3rd
party systems is a common use case for custom environment checks.

	:::php
	<?php

	class MyGatewayCheck implements EnvironmentCheck {
		protected $checkTable;

		function check() {
			$g = new MyGateway;
			
			$response = $g->call();
			$expectedResponse = "foo";
			
			if($response == null) {
				return array(EnvironmentCheck::ERROR, "MyGateway didn't return a response");
			} else if($response != $expectedResponse) {
				return array(EnvironmentCheck::WARNING, "MyGateway returned unexpected response $response");
			} else {
				return array(EnvironmentCheck::OK, "");
			}
		}
	}
	
Once you have created your custom check class, don't forget to register it in a check suite
	
	:::php
	EnvironmentCheckSuite::register('check', 'MyGatewayCheck', "Can I connect to the gateway?");

### Using other environment check suites

If you want to use the same UI as dev/health and dev/check, you can create an `EnvironmentChecker` object.  This class
is a `RequestHandler` and so can be returned from an action handler.  The first argument to the `EnvironmentChecker`
constructor is the suite name. For example:

	<?php

	class DevHealth extends Controller {

		function index() {
			$e = new EnvironmentChecker('health', 'Site health');
			return $e;
		}
	}
	
If you wish to embed an environment check suite in another, you can use the following call.

	$result = EnvironmentCheckSuite::inst("health")->run();
	
`$result` will contain a `EnvironmentCheckSuiteResult` object

 * `$result->ShouldPass()`: Return a boolean of whether or not the tests passed.
 * `$result->Status()`: The string "OK", "WARNING", or "ERROR", depending on the worst failure.
 * `$result->Details()`: A `DataObjectSet` of details about the result of each check in the suite.

See `EnvironmentChecker.ss` to see how these can be used to build a UI.

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will be able to upgrade to any minor or patch version of this library without any breaking changes to the public API. Semver also requires that we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All other methods are not part of the public API. Where possible, we'll try to keep `protected` methods backwards-compatible in minor/patch versions, but if you're overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](http://github.com/silverstripe-labs/silverstripe-environmentalcheck/issues) for any bugs you've found, or features you're missing.
