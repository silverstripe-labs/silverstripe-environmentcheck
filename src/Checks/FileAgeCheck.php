<?php

namespace SilverStripe\EnvironmentCheck\Checks;

use SilverStripe\EnvironmentCheck\EnvironmentCheck;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Checks for the maximum age of one or more files or folders.
 * Useful for files which should be frequently auto-generated,
 * like static caches, as well as for backup files and folders.
 * Does NOT check for existence of a file (will silently fail).
 *
 * Examples:
 * // Checks that Requirements::combine_files() has regenerated files in the last 24h
 * EnvironmentCheckSuite::register(
 *  'check',
 *  'FileAgeCheck("' . ASSETS_PATH . '/_combined_files/*.js' . '", "-1 day", '>', " . FileAgeCheck::CHECK_ALL) . "'
 * );
 *
 * // Checks that at least one backup folder has been created in the last 24h
 * EnvironmentCheckSuite::register(
 *  'check',
 *  'FileAgeCheck("' . BASE_PATH . '/../backups/*' . '", "-1 day", '>', " . FileAgeCheck::CHECK_SINGLE) . "'
 * );
 *
 * @package environmentcheck
 */
class FileAgeCheck implements EnvironmentCheck
{
    /**
     * @var int
     */
    const CHECK_SINGLE = 1;

    /**
     * @var int
     */
    const CHECK_ALL = 2;

    /**
     * Absolute path to a file or folder, compatible with glob().
     *
     * @var string
     */
    protected $path;

    /**
     * Relative date specification, compatible with strtotime().
     *
     * @var string
     */
    protected $relativeAge;

    /**
     * The function to use for checking file age: so filemtime(), filectime(), or fileatime().
     *
     * @var string
     */
    protected $checkFn;

    /**
     * Constant, check for a single file to match age criteria, or all of them.
     *
     * @var int
     */
    protected $checkType;

    /**
     * Type of comparison (either > or <).
     *
     * @var string
     */
    protected $compareOperand;

    /**
     * @param string $path
     * @param string $relativeAge
     * @param string $compareOperand
     * @param null|int $checkType
     * @param string $checkFn
     */
    public function __construct($path, $relativeAge, $compareOperand = '>', $checkType = null, $checkFn = 'filemtime')
    {
        $this->path = $path;
        $this->relativeAge = $relativeAge;
        $this->checkFn = $checkFn;
        $this->checkType = ($checkType) ? $checkType : FileAgeCheck::CHECK_SINGLE;
        $this->compareOperand = $compareOperand;
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function check()
    {
        $cutoffTime =  strtotime($this->relativeAge ?? '', DBDatetime::now()->Format('U'));
        $files = $this->getFiles();
        $invalidFiles = [];
        $validFiles = [];
        $checkFn = $this->checkFn;
        $allValid = true;
        if ($files) {
            foreach ($files as $file) {
                $fileTime = $checkFn($file);
                $valid = ($this->compareOperand == '>') ? ($fileTime >= $cutoffTime) : ($fileTime <= $cutoffTime);
                if ($valid) {
                    $validFiles[] = $file;
                } else {
                    $invalidFiles[] = $file;
                    if ($this->checkType == FileAgeCheck::CHECK_ALL) {
                        return [
                            EnvironmentCheck::ERROR,
                            sprintf(
                                'File "%s" doesn\'t match age check (compare %s: %s, actual: %s)',
                                $file,
                                $this->compareOperand,
                                date('c', $cutoffTime),
                                date('c', $fileTime)
                            )
                        ];
                    }
                }
            }
        }

        // If at least one file was valid, count as passed
        if ($this->checkType == FileAgeCheck::CHECK_SINGLE && count($invalidFiles ?? []) < count($files ?? [])) {
            return [EnvironmentCheck::OK, ''];
        }
        if (count($invalidFiles ?? []) == 0) {
            return [EnvironmentCheck::OK, ''];
        }
        return [
            EnvironmentCheck::ERROR,
            sprintf('No files matched criteria (%s %s)', $this->compareOperand, date('c', $cutoffTime))
        ];
    }

    /**
     * Gets a list of absolute file paths.
     *
     * @return array
     */
    protected function getFiles()
    {
        return glob($this->path ?? '');
    }
}
