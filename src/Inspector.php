<?php

declare(strict_types=1);
/*
 * Forwards compatility really; everybody will to this once.
 * But scalar parameter type declaration is no-go until then; coercion or TypeError(?).
 */

namespace SimpleComplex\Inspect;

/**
 * @package SimpleComplex\Inspect
 */
class Inspector
{


    /**
     * @var integer
     */
    const ERROR_ALGORITHM = 100;

    /**
     * @var integer
     */
    const ERROR_USER = 101;

    /**
     * @var integer
     */
    const ERROR_OUTPUTLENGTH = 102;

    /**
     * @var integer
     */
    const ERROR_EXECTIME = 103;

    /**
     * @var string
     */
    const LOG_TYPE = 'inspect';

    /**
     * Maximum sub var recursion depth.
     *
     * @var integer
     */
    const DEPTH_MAX = 20;

    /**
     * Default sub var recursion depth.
     *
     * @var integer
     */
    const DEPTH_DEFAULT = 10;

    /**
     * Default sub var recursion depth, when tracer inspects
     * function/method arguments.
     *
     * @var integer
     */
    const TRACE_DEPTH_DEFAULT = 2;

    /**
     * Absolute maximum stack frame depth.
     *
     * @var integer
     */
    const TRACE_LIMIT_MAX = 100;

    /**
     * Default stack frame depth.
     *
     * @var integer
     */
    const TRACE_LIMIT_DEFAULT = 5;

    /**
     * Minimum string truncation.
     *
     * @var integer
     */
    const TRUNCATE_MIN = 100000;

    /**
     * Default string truncation.
     *
     * @var integer
     */
    const TRUNCATE_DEFAULT = 1000;

    /**
     * Absolute max. length of an inspection/trace output.
     *
     * Doesn't apply when logging to standard log (PHP error_log()); then 1Kb if
     * syslog and 4Kb if file log.
     *
     * @var integer
     */
    const OUTPUT_MAX = 2097152;

    /**
     * Default max. length of an inspection/trace output.
     *
     * @var integer
     */
    const OUTPUT_DEFAULT = 1048576;

    /**
     * Current cumulative string length of the inspection output.
     *
     * @var integer
     */
    public $outputLength = 0;

    /**
     * @var string
     *  Values: variable|trace.
     */
    protected $kind;

    /**
     * Options:
     * - depth
     *
     * @param $mixed $var
     *
     * @return string
     */
    public function variable($var) {
        return '';
    }

    /**
     * Options:
     * - depth
     *
     *
     * @param \Throwable|null $errorOrBackTrace
     *
     * @return string
     */
    public function trace($errorOrBackTrace = null) {
        if ($errorOrBackTrace) {
            if (!is_object($errorOrBackTrace) && is_a($errorOrBackTrace, \Throwable::class)) {

            }
        }
        return '';
    }

    /**
     * @var array
     */
    protected $options;

    /**
     * (variable) Current object/array bucket key name.
     *
     * @var string
     */
    protected $variableKey;

    /**
     * (trace) Flag that frame limit got reduced due to too long output.
     *
     * @var integer
     */
    protected $traceLimitReduced;

    /**
     * Maximum object/array recursion depth.
     *
     * @var integer
     */
    protected $depth;


    public function($logger = null) {

    }
}
