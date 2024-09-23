<?php

namespace SilverStripe\EnvironmentCheck\Checks;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Validation\ValidationResult;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\EnvironmentCheck\Traits\Fetcher;
use SilverStripe\EnvironmentCheck\EnvironmentCheck;

/**
 * Check cache headers for any response, can specify directives that must be included and
 * also must be excluded from Cache-Control headers in response. Also checks for
 * existence of ETag.
 *
 * @example SilverStripe\EnvironmentCheck\Checks\CacheHeadersCheck("/",["must-revalidate", "max-age=120"],["no-store"])
 * @package environmentcheck
 */
class CacheHeadersCheck implements EnvironmentCheck
{
    use Fetcher;

    /**
     * Settings that must be included in the Cache-Control header
     *
     * @var array
     */
    protected $mustInclude = [];

    /**
     * Settings that must be excluded in the Cache-Control header
     *
     * @var array
     */
    protected $mustExclude = [];

    /**
     * Result to keep track of status and messages for all checks, reuses
     * ValidationResult for convenience.
     *
     * @var ValidationResult
     */
    protected $result;

    /**
     * Set up with URL, arrays of header settings to check.
     *
     * @param string $url
     * @param array $mustInclude Settings that must be included in Cache-Control
     * @param array $mustExclude Settings that must be excluded in Cache-Control
     */
    public function __construct($url = '', $mustInclude = [], $mustExclude = [])
    {
        $this->setURL($url);
        $this->mustInclude = $mustInclude;
        $this->mustExclude = $mustExclude;
    }

    /**
     * Check that correct caching headers are present.
     *
     * @return void
     */
    public function check()
    {
        // Using a validation result to capture messages
        $this->result = new ValidationResult();

        $response = $this->client->get($this->getURL());
        $fullURL = $this->getURL();
        if ($response === null) {
            return [
                EnvironmentCheck::ERROR,
                "Cache headers check request failed for $fullURL",
            ];
        }

        //Check that Etag exists
        $this->checkEtag($response);

        // Check Cache-Control settings
        $this->checkCacheControl($response);

        if ($this->result->isValid()) {
            return [
                EnvironmentCheck::OK,
                $this->getMessage(),
            ];
        } else {
            return [
                EnvironmentCheck::ERROR,
                $this->getMessage(),
            ];
        }
    }

    /**
     * Collate messages from ValidationResult so that it is clear which parts
     * of the check passed and which failed.
     *
     * @return string
     */
    private function getMessage()
    {
        $ret = '';
        // Filter good messages
        $goodTypes = [ValidationResult::TYPE_GOOD, ValidationResult::TYPE_INFO];
        $good = array_filter(
            $this->result->getMessages() ?? [],
            function ($val, $key) use ($goodTypes) {
                if (in_array($val['messageType'], $goodTypes ?? [])) {
                    return true;
                }
                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );
        if (!empty($good)) {
            $ret .= "GOOD: " . implode('; ', array_column($good ?? [], 'message')) . " ";
        }

        // Filter bad messages
        $badTypes = [ValidationResult::TYPE_ERROR, ValidationResult::TYPE_WARNING];
        $bad = array_filter(
            $this->result->getMessages() ?? [],
            function ($val, $key) use ($badTypes) {
                if (in_array($val['messageType'], $badTypes ?? [])) {
                    return true;
                }
                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );
        if (!empty($bad)) {
            $ret .= "BAD: " . implode('; ', array_column($bad ?? [], 'message'));
        }
        return $ret;
    }

    /**
     * Check that ETag header exists
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function checkEtag(ResponseInterface $response)
    {
        $eTag = $response->getHeaderLine('ETag');
        $fullURL = Controller::join_links(Director::absoluteBaseURL(), $this->url);

        if ($eTag) {
            $this->result->addMessage(
                "$fullURL includes an Etag header in response",
                ValidationResult::TYPE_GOOD
            );
            return;
        }
        $this->result->addError(
            "$fullURL is missing an Etag header",
            ValidationResult::TYPE_WARNING
        );
    }

    /**
     * Check that the correct header settings are either included or excluded.
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function checkCacheControl(ResponseInterface $response)
    {
        $cacheControl = $response->getHeaderLine('Cache-Control');
        $vals = array_map('trim', explode(',', $cacheControl ?? ''));
        $fullURL = Controller::join_links(Director::absoluteBaseURL(), $this->url);

        // All entries from must contain should be present
        if ($this->mustInclude == array_intersect($this->mustInclude ?? [], $vals)) {
            $matched = implode(",", $this->mustInclude);
            $this->result->addMessage(
                "$fullURL includes all settings: {$matched}",
                ValidationResult::TYPE_GOOD
            );
        } else {
            $missing = implode(",", array_diff($this->mustInclude ?? [], $vals));
            $this->result->addError(
                "$fullURL is excluding some settings: {$missing}"
            );
        }

        // All entries from must exclude should not be present
        if (empty(array_intersect($this->mustExclude ?? [], $vals))) {
            $missing = implode(",", $this->mustExclude);
            $this->result->addMessage(
                "$fullURL excludes all settings: {$missing}",
                ValidationResult::TYPE_GOOD
            );
        } else {
            $matched = implode(",", array_intersect($this->mustExclude ?? [], $vals));
            $this->result->addError(
                "$fullURL is including some settings: {$matched}"
            );
        }
    }
}
