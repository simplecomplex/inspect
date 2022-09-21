<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect\Helper;

/**
 * Unicode string methods.
 *
 * @package SimpleComplex\Inspect
 */
class Unicode implements UnicodeInterface
{
    /**
     * @var bool
     */
    protected $extMbString;

    /**
     */
    public function __construct()
    {
        $this->extMbString = function_exists('mb_strlen');
    }

    /**
     * Valid UTF-8.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function validate($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        return $v === '' ? true :
            // The PHP regex u modifier forces the whole subject to be evaluated
            // as UTF-8. And if any byte sequence isn't valid UTF-8 preg_match()
            // will return zero for no-match.
            // The s modifier makes dot match newline; without it a string consisting
            // of a newline solely would result in a false negative.
            !!preg_match('/./us', $v);
    }

    /**
     * Multibyte-safe string length.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return int
     */
    public function strlen($var) : int
    {
        $v = '' . $var;
        if ($v === '') {
            return 0;
        }
        if ($this->extMbString) {
            return mb_strlen($v);
        }

        $n = 0;
        $le = strlen($v);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($v[$i])) < 128) {
                ++$n;
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) {
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    ++$n;
                }
                $leading = true;
            }
        }
        return $n;
    }

    /**
     * @param string $haystack
     *      Gets stringified.
     * @param string $needle
     *      Gets stringified.
     *
     * @return bool|int
     *      False: if needle not found, or if either arg evaluates to empty string.
     */
    public function strpos($haystack, $needle): bool|int
    {
        $hstck = '' . $haystack;
        $ndl = '' . $needle;
        if ($hstck === '' || $ndl === '') {
            return false;
        }
        if ($this->extMbString) {
            return mb_strpos($hstck, $ndl);
        }

        $pos = strpos($hstck, $ndl);
        if (!$pos) {
            return $pos;
        }
        return count(
            preg_split('//u', substr($hstck, 0, $pos), -1, PREG_SPLIT_NO_EMPTY)
        );
    }

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
     *
     * @throws \InvalidArgumentException
     *      Bad arg start or length.
     */
    public function substr($var, int $start, ?int $length = null) : string
    {
        if ($start < 0) {
            throw new \InvalidArgumentException('Arg start is not non-negative integer.');
        }
        if ($length !== null && (!is_int($length) || $length < 0)) {
            throw new \InvalidArgumentException('Arg length is not non-negative integer or null.');
        }
        $v = '' . $var;
        if (!$length || $v === '') {
            return '';
        }
        if ($this->extMbString) {
            return mb_substr($v, $start, $length);
        }

        // The actual algo (further down) only works when start is zero.
        if ($start > 0) {
            // Trim off chars before start.
            $v = substr($v,
                strlen(
                    // Offsets multibyte string length.
                    $this->substr($v, 0, $start)
                )
            );
        }

        $n = 0;
        $le = strlen($v);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($v[$i])) < 128) {
                if ((++$n) > $length) {
                    return substr($v, 0, $i);
                }
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) { // continuation char
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    if ((++$n) > $length) {
                        return substr($v, 0, $i);
                    }
                }
                $leading = true;
            }
        }
        return $v;
    }

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
     *
     * @throws \InvalidArgumentException
     *      Bad arg length.
     */
    public function truncateToByteLength($var, int $length): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Arg length is not non-negative integer.');
        }

        $v = '' . $var;
        if (strlen($v) <= $length) {
            return $v;
        }

        // Truncate to UTF-8 char length (>= byte length).
        $v = $this->substr($v, 0, $length);
        // If all ASCII.
        if (($le = strlen($v)) == $length) {
            return $v;
        }

        // This algo will truncate one UTF-8 char too many,
        // if the string ends with a UTF-8 char, because it doesn't check
        // if a sequence of continuation bytes is complete.
        // Thus the check preceding this algo (actual byte length matches
        // required max length) is vital.
        do {
            --$le;
            // String not valid UTF-8, because never found an ASCII or leading UTF-8
            // byte to break before.
            if ($le < 0) {
                return '';
            }
            // An ASCII byte.
            elseif (($ord = ord($v[$le])) < 128) {
                // We can break before an ASCII byte.
                $ascii = true;
                $leading = false;
            }
            // A UTF-8 continuation byte.
            elseif ($ord < 192) {
                $ascii = $leading = false;
            }
            // A UTF-8 leading byte.
            else {
                $ascii = false;
                // We can break before a leading UTF-8 byte.
                $leading = true;
            }
        } while($le > $length || (!$ascii && !$leading));

        return substr($v, 0, $le);
    }
}
