<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2017-2023 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect\Helper;

interface UnicodeInterface
{
    /**
     * Valid UTF-8.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function validate(mixed $subject): bool;

    /**
     * Multibyte-safe string length.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return int
     */
    public function strlen(mixed $var): int;

    /**
     * @param string $haystack
     *      Gets stringified.
     * @param string $needle
     *      Gets stringified.
     *
     * @return bool|int
     *      False: if needle not found, or if either arg evaluates to empty string.
     */
    public function strpos(mixed $haystack, mixed $needle): bool|int;

    /**
     * Multibyte-safe sub string.
     *
     * Does not check if arg $v is valid UTF-8.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $start
     * @param int|null $length
     *      Default: null; until end of arg str.
     *
     * @return string
     */
    public function substr(mixed $var, int $start, ?int $length = null): string;

    /**
     * Truncate multibyte safe until ~ASCII length is equal to/less than arg
     * length.
     *
     * Does not check if arg $v is valid UTF-8.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $length
     *      Byte length (~ ASCII char length).
     *
     * @return string
     */
    public function truncateToByteLength(mixed $var, int $length): string;
}
