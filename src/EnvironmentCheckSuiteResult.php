<?php

namespace SilverStripe\EnvironmentCheck;

use InvalidArgumentException;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\ModelData;

/**
 * A single set of results from running an EnvironmentCheckSuite
 *
 * @package environmentcheck
 */
class EnvironmentCheckSuiteResult extends ModelData
{
    /**
     * @var ArrayList
     */
    protected $details;

    /**
     * @var int
     */
    protected $worst = 0;

    public function __construct()
    {
        parent::__construct();
        $this->details = new ArrayList();
    }

    /**
     * @param int $status
     * @param string $message
     * @param string $checkIdentifier
     */
    public function addResult($status, $message, $checkIdentifier)
    {
        $this->details->push(new ArrayData([
            'Check' => $checkIdentifier,
            'Status' => $this->statusText($status),
            'StatusCode' => $status,
            'Message' => $message,
        ]));

        $this->worst = max($this->worst, $status);
    }

    /**
     * Returns true if there are no errors.
     *
     * @return bool
     */
    public function ShouldPass()
    {
        return $this->worst <= EnvironmentCheck::WARNING;
    }

    /**
     * Returns overall (i.e. worst) status as a string.
     *
     * @return string
     */
    public function Status()
    {
        return $this->statusText($this->worst);
    }

    /**
     * Returns detailed status information about each check.
     *
     * @return ArrayList
     */
    public function Details()
    {
        return $this->details;
    }

    /**
     * Convert the final result status and details to JSON.
     *
     * @return string
     */
    public function toJSON()
    {
        $result = [
            'Status' => $this->Status(),
            'ShouldPass' => $this->ShouldPass(),
            'Checks' => []
        ];
        foreach ($this->details as $detail) {
            $result['Checks'][] = $detail->toMap();
        }
        return json_encode($result);
    }

    /**
     * Return a text version of a status code.
     *
     * @param  int $status
     * @return string
     * @throws InvalidArgumentException
     */
    protected function statusText($status)
    {
        switch ($status) {
            case EnvironmentCheck::ERROR:
                return 'ERROR';
                break;
            case EnvironmentCheck::WARNING:
                return 'WARNING';
                break;
            case EnvironmentCheck::OK:
                return 'OK';
                break;
            case 0:
                return 'NO CHECKS';
                break;
            default:
                throw new InvalidArgumentException("Bad environment check status '$status'");
                break;
        }
    }
}
