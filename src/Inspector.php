<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Inspect;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Filter\Unicode;
use SimpleComplex\Filter\Sanitize;

/*
 * Options no longer supported:
 * - message (use a logger for that instead)
 * - hide_scalars (never used)
 * - hide_paths (no longer optional, and only document root)
 * - by_user (not applicable, logger may to it)
 * - one_lined (never uses)
 * - no_fileline (stupid)
 * - name (daft)
 *
 * Arg options can no longer be an object.
 *
 * Arg options as string is now 'kind', not obsolete 'message'.
 */


/**
 * @package SimpleComplex\Inspect
 */
class Inspector
{
    /**
     * @var string
     */
    const LOG_TYPE = 'inspect';

    /**
     * @var integer
     */
    const ERROR_OUTPUTLENGTH = 102;

    /**
     * @var integer
     */
    const ERROR_EXECTIME = 103;

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
     * Maximum (longest) string truncation.
     *
     * @var integer
     */
    const TRUNCATE_MAX = 100000;

    /**
     * Default string truncation.
     *
     * @var integer
     */
    const TRUNCATE_DEFAULT = 1000;

    /**
     * Absolute max. length of an inspection/trace output.
     *
     * @var integer
     */
    const OUTPUT_MAX = 2097152;

    /**
     * Default maximum length of an inspection/trace output.
     *
     * @var integer
     */
    const OUTPUT_DEFAULT = 1048576;

    /**
     * String variable str_replace() needles.
     */
    const NEEDLES = [
        "\0", "\n", "\r", "\t", '<', '>', '"', "'",
    ];

    /**
     * String variable str_replace() replacers.
     */
    const REPLACERS = [
        '_NUL_', '_NL_', '_CR_', '_TB_', '&#60;', '&#62;', '&#34;', '&#39;',
    ];

    /**
     * Formatting.
     */
    const FORMAT = [
        'newline' => "\n",
        'delimiter' => "\n",
        'indent' => '.  ',
        'quote' => '`',
        'encloseTag' => 'pre',
    ];

    /**
     * Formatting when tracing; gets merged over FORMAT.
     */
    const FORMAT_TRACE = [
        'delimiter' => "\n  ",
        'spacer' => '- - - - - - - - - - - - - - - - - - - - - - - - -',
    ];

    /**
     * @var array
     */
    protected $options = array(
        'kind' => '',
        'logger' => null,
        'code' => 0,
        'depth' => 0,
        'limit' => 0,
        'truncate' => 0,
        'skipKeys' => [],
        'needles' => [],
        'replacers' => [],
        'outputMax' => 0,
        'wrappers' => 0,
    );


    /**
     * @var string
     *  Values: variable|trace.
     */
    protected $kind = 'variable';

    /**
     * Current cumulative string length of the inspection output.
     *
     * @var integer
     */
    protected $outputLength = 0;

    /**
     * Maximum object/array recursion depth.
     *
     * @var integer
     */
    protected $depth;

    /**
     * (variable) Current object/array bucket key name.
     *
     * @var string
     */
    protected $key;

    /**
     * (trace) Flag that frame limit got reduced due to too long output.
     *
     * @var integer
     */
    protected $traceLimitReduced;

    /**
     * @var CacheInterface|null
     */
    protected $config;

    /**
     * @var Unicode
     */
    protected $unicode;

    /**
     * @var Sanitize
     */
    protected $sanitize;

    /**
     * Do not call this directly, use Inspect instead.
     *
     * Does not check dependencies; Inspect constructor does that.
     *
     * @see JsonLog::__construct()
     * @see JsonLog::log()
     *
     * @param array $dependencies {
     *      @var CacheInterface|null $config
     *      @var Unicode $unicode
     *      @var Sanitize $sanitize
     * }
     * @param mixed $subject
     * @param array|integer|string $options
     *   Integer when inspecting variable: maximum depth.
     *   Integer when tracing: stack frame limit.
     *   String: kind (variable|trace); otherwise ignored.
     *   Not array|integer|string: ignored.
     */
    public function __construct(array $dependencies, $subject, $options = []) {
        $this->config = $dependencies['config'];
        $this->unicode = $dependencies['unicode'];
        $this->sanitize = $dependencies['sanitize'];

        $kind = '';
        $depth_or_limit = 0;
        $use_arg_options = $trace = $back_trace = false;
        if ($options) {
            $type = gettype($options);
            switch ($type) {
                case 'array':
                    if (!empty($options['kind'])) {
                        switch ('' . $options['kind']) {
                            case 'variable':
                                $kind = 'variable';
                                break;
                            case 'trace':
                                $trace = true;
                                $kind = 'trace';
                                break;
                        }
                    }
                    unset($options['kind']);
                    $use_arg_options = !!$options;
                    break;
                case 'integer':
                    // Depends on kind.
                    $depth_or_limit = $options;
                    break;
                case 'string':
                    // Kind.
                    switch ($options) {
                        case 'variable':
                            $kind = 'variable';
                            break;
                        case 'trace':
                            $trace = true;
                            $kind = 'trace';
                            break;
                        default:
                            // Ignore.
                    }
                    break;
                default:
                    // Ignore.
            }
        }

        // Establish kind - variable|trace - before preparing options.----------
        if (!$kind) {
            // No kind + exception|error: do trace.
            if ($subject && is_object($subject) && $subject instanceof \Throwable) {
                $trace = true;
                $kind = 'trace';
            } else {
                $kind = 'variable';
            }
        } elseif ($trace) {
            // Trace + not exception|error: do back trace.
            if (!$subject || !is_object($subject) || !$subject instanceof \Throwable) {
                $back_trace = true;
            }
        }

        // Prepare options.-----------------------------------------------------
        $opts =& $this->options;
        $opts['kind'] = $kind;
        // logger.
        if ($use_arg_options && !empty($options['logger'])) {
            // No type checking;
            // we trust that folks passing logger know what they do.
            $opts['logger'] = $options['logger'];
        }
        // code.
        if ($use_arg_options && !empty($options['code'])) {
            if (($tmp = (int) $options['code']) > 0) {
                $opts['code'] = $tmp;
            }
        }
        // depth.
        if (!$trace && $depth_or_limit && $depth_or_limit <= static::DEPTH_MAX) {
            $opts['depth'] = $depth_or_limit;
        }
        else {
            $opts['depth'] = !$trace ? static::DEPTH_DEFAULT : static::TRACE_DEPTH_DEFAULT;
            if ($use_arg_options && !empty($options['depth'])) {
                if (($tmp = (int) $options['depth']) > 0 && $tmp <= static::DEPTH_MAX) {
                    $opts['depth'] = $tmp;
                }
            }
        }
        // limit; only used when tracing.
        if (!$trace) {
            unset($opts['limit']);
        } elseif ($depth_or_limit && $depth_or_limit <= static::TRACE_LIMIT_MAX) {
            $opts['limit'] = $depth_or_limit;
        } else {
            $opts['limit'] = static::TRACE_LIMIT_DEFAULT;
            if ($use_arg_options && empty($options['limit'])) {
                if (($tmp = (int) $options['limit']) > 0 && $tmp <= static::TRACE_LIMIT_MAX) {
                    $opts['limit'] = $tmp;
                }
            }
        }
        // truncate.
        $opts['truncate'] = static::TRUNCATE_DEFAULT;
        if ($use_arg_options && !empty($options['truncate'])) {
            if (($tmp = (int) $options['truncate']) >= 0 && $tmp <= static::TRUNCATE_MAX) {
                $opts['truncate'] = $tmp;
            }
        }
        // skipKeys.
        if ($use_arg_options && !empty($options['skipKeys'])) {
            if (is_array($options['skipKeys'])) {
                $opts['skipKeys'] = $options['skipKeys'];
            } else {
                $opts['skipKeys'] = [
                    // Stringify.
                    '' . $options['skipKeys']
                ];
            }
        }
        // replacers.
        $opts['replacers'] = static::REPLACERS;
        $any_opt_replacers = false;
        if ($use_arg_options && !empty($options['replacers']) && is_array($options['replacers'])) {
            $any_opt_replacers = true;
            $opts['replacers'] = $options['replacers'];
        }
        // needles; only arg options override if arg options replacers.
        $opts['needles'] = static::NEEDLES;
        if ($any_opt_replacers && !empty($options['needles']) && is_array($options['needles'])) {
            $opts['needles'] = $options['needles'];
        }
        // outputMax.
        $opts['outputMax'] = static::OUTPUT_DEFAULT;
        if ($use_arg_options && !empty($options['outputMax'])) {
            if (($tmp = (int) $options['outputMax']) > 0 && $tmp <= static::OUTPUT_MAX) {
                $opts['outputMax'] = $tmp;
            }
        }
        // wrappers.
        if ($use_arg_options && !empty($options['wrappers'])) {
            if (($tmp = (int) $options['wrappers']) > 0) {
                $opts['wrappers'] = $tmp;
            }
        }
        

        if ($trace) {
            $this->trc(!$back_trace ? $subject : null);
        }
    }

    /**
     * @param \Throwable|null $throwableOrNull
     */
    protected function trc($throwableOrNull) {

    }
}
