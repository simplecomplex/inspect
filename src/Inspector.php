<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Inspect;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Filter\Unicode;
use SimpleComplex\Filter\Sanitize;
use SimpleComplex\Validate\Validate;
use SimpleComplex\Inspect\Exception\LogicException;

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
 * @internal
 *
 * @package SimpleComplex\Inspect
 */
class Inspector
{
    /**
     * Conf var default namespace.
     *
     * @var string
     */
    const CONFIG_DOMAIN = 'lib_simplecomplex_inspect';

    /**
     * Delimiter between config domain and config var name, when not using
     * environment vars.
     *
     * @var string
     */
    const CONFIG_DELIMITER = ':';

    /**
     * @var int
     */
    const ERROR_EXECTIME = 103;

    /**
     * Maximum sub var recursion depth.
     *
     * @var int
     */
    const DEPTH_MAX = 20;

    /**
     * Default sub var recursion depth.
     *
     * @var int
     */
    const DEPTH_DEFAULT = 10;

    /**
     * Default sub var recursion depth, when tracer inspects
     * function/method arguments.
     *
     * @var int
     */
    const TRACE_DEPTH_DEFAULT = 2;

    /**
     * Absolute maximum stack frame depth.
     *
     * @var int
     */
    const TRACE_LIMIT_MAX = 100;

    /**
     * Default stack frame depth.
     *
     * @var int
     */
    const TRACE_LIMIT_DEFAULT = 5;

    /**
     * Maximum (longest) string truncation.
     *
     * @var int
     */
    const TRUNCATE_MAX = 100000;

    /**
     * Default string truncation.
     *
     * @var int
     */
    const TRUNCATE_DEFAULT = 1000;

    /**
     * Absolute max. length of an inspection/trace output.
     *
     * @var int
     */
    const OUTPUT_MAX = 2097152;

    /**
     * Default maximum length of an inspection/trace output.
     *
     * @var int
     */
    const OUTPUT_DEFAULT = 1048576;

    /**
     * For stuff unaccounted for.
     *
     * @var int
     */
    const OUTPUT_MARGIN = 512;

    /**
     * Used as percentage.
     *
     * @see Inspector::abortExecutionTimeExceeded()
     *
     * @var int
     */
    const EXEC_TIMEOUT_MAX = 95;

    /**
     * @var int
     */
    const EXEC_TIMEOUT_DEFAULT = 90;

    /**
     * String variable str_replace() needles.
     *
     * @var string[]
     */
    const NEEDLES = [
        "\0", "\n", "\r", "\t", '<', '>', '"', "'",
    ];

    /**
     * String variable str_replace() replacers.
     *
     * @var string[]
     */
    const REPLACERS = [
        '_NUL_', '_NL_', '_CR_', '_TB_', '&#60;', '&#62;', '&#34;', '&#39;',
    ];

    /**
     * List of object/array string elements whose value should truncated to zero.
     *
     * @var string[]
     */
    const HIDE_VALUE_OF_KEYS = [
        'pw',
        'pass',
        'password',
    ];

    /**
     * Formatting.
     *
     * @var string[]
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
     *
     * @var string[]
     */
    const FORMAT_TRACE = [
        'delimiter' => "\n  ",
        'spacer' => '- - - - - - - - - - - - - - - - - - - - - - - - -',
    ];

    /**
     * @var string[]
     */
    const LIB_FILENAMES = [
        'Inspect.php',
        'Inspector.php',
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
        'skip_keys' => [],
        'needles' => [],
        'replacers' => [],
        'output_max' => 0,
        'exectime_percent' => 0,
        'wrappers' => 0,
    );

    /**
     * @var string
     *  Values: variable|trace.
     */
    protected $kind = 'variable';


    // Control vars.------------------------------------------------------------

    protected $abort = false;

    protected $warnings = [];

    protected $nspctCall = 0;


    // Output properties.-------------------------------------------------------

    protected $preface = '';

    protected $output = '';

    protected $length = 0;

    protected $fileLine = '';

    protected $code = 0;


    // Constructor arg equivalents.---------------------------------------------

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
     * @var Validate
     */
    protected $validate;

    /**
     * Do not call this directly, use Inspect instead.
     *
     * Does not check dependencies; Inspect constructor does that.
     *
     * @see Inspect::__construct()
     * @see Inspect::getInstance()
     *
     * @internal
     *
     * @param array $dependencies {
     *      @var CacheInterface|null $config  Optional, null will do.
     *      @var Unicode $unicode  Required.
     *      @var Sanitize $sanitize  Required.
     *      @var Validate $validate  Required.
     * }
     * @param mixed $subject
     * @param array|int|string $options
     *   Integer when inspecting variable: maximum depth.
     *   Integer when tracing: stack frame limit.
     *   String: kind (variable|trace); otherwise ignored.
     *   Not array|integer|string: ignored.
     */
    public function __construct(array $dependencies, $subject, $options = []) {
        $this->config = $dependencies['config'];
        $this->unicode = $dependencies['unicode'];
        $this->sanitize = $dependencies['sanitize'];
        $this->validate = $dependencies['validate'];

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
        // Keep default: null.
        // code.
        // Keep default: zero.
        // depth.
        if (!$trace && $depth_or_limit && $depth_or_limit <= static::DEPTH_MAX) {
            $opts['depth'] = $depth_or_limit;
        } else {
            $opts['depth'] = !$trace ? static::DEPTH_DEFAULT : static::TRACE_DEPTH_DEFAULT;
        }
        // limit; only used when tracing.
        if (!$trace) {
            unset($opts['limit']);
        } elseif ($depth_or_limit && $depth_or_limit <= static::TRACE_LIMIT_MAX) {
            $opts['limit'] = $depth_or_limit;
        } else {
            $opts['limit'] = ($tmp = $this->configGet(static::CONFIG_DOMAIN, 'trace_limit')) ?
                (int) $tmp : static::TRACE_LIMIT_DEFAULT;
        }
        // truncate.
        $opts['truncate'] = (int) ($tmp = $this->configGet(static::CONFIG_DOMAIN, 'truncate')) ?
            (int) $tmp : static::TRUNCATE_DEFAULT;
        // skip_keys.
        // Keep default: empty array.
        // replacers.
        $opts['replacers'] = static::REPLACERS;
        // needles; only arg options override if arg options replacers.
        $opts['needles'] = static::NEEDLES;
        // output_max.
        $opts['output_max'] = ($tmp = $this->configGet(static::CONFIG_DOMAIN, 'output_max')) ?
            (int) $tmp : static::OUTPUT_DEFAULT;
        // exectime_percent.
        $opts['exectime_percent'] = ($tmp = $this->configGet(static::CONFIG_DOMAIN, 'exectime_percent')) ?
            (int) $tmp : static::EXEC_TIMEOUT_DEFAULT;
        // wrappers.
        // Keep default: zero.

        // Overriding options by argument.--------------------------------------
        if ($use_arg_options) {
            if (!empty($options['logger'])) {
                // No type checking;
                // we trust that folks passing logger know what they do.
                $opts['logger'] = $options['logger'];
            }

            if (
                !empty($options['code'])
                && ($tmp = (int) $options['code']) > 0
            ) {
                $opts['code'] = $tmp;
            }

            if (
                !empty($options['depth'])
                && ($tmp = (int) $options['depth']) > 0 && $tmp <= static::DEPTH_MAX
            ) {
                $opts['depth'] = $tmp;
            }

            if (
                empty($options['limit'])
                && ($tmp = (int) $options['limit']) > 0 && $tmp <= static::TRACE_LIMIT_MAX
            ) {
                $opts['limit'] = $tmp;
            }

            if (
                isset($options['truncate'])
                && ($tmp = (int) $options['truncate']) >= 0 && $tmp <= static::TRUNCATE_MAX
            ) {
                $opts['truncate'] = $tmp;
            }

            if (!empty($options['skip_keys'])) {
                if (is_array($options['skip_keys'])) {
                    $opts['skip_keys'] = $options['skip_keys'];
                } else {
                    $opts['skip_keys'] = [
                        // Stringify.
                        '' . $options['skip_keys']
                    ];
                }
            }

            $any_opt_replacers = false;
            if (!empty($options['replacers']) && is_array($options['replacers'])) {
                $any_opt_replacers = true;
                $opts['replacers'] = $options['replacers'];
            }

            if ($any_opt_replacers && !empty($options['needles']) && is_array($options['needles'])) {
                $opts['needles'] = $options['needles'];
            }

            if (
                !empty($options['output_max'])
                && ($tmp = (int) $options['output_max']) > 0 && $tmp <= static::OUTPUT_MAX
            ) {
                    $opts['output_max'] = $tmp;
            }

            if (
                !empty($options['exectime_percent'])
                && ($tmp = (int) $options['exectime_percent']) > 0 && $tmp <= static::EXEC_TIMEOUT_MAX
            ) {
                $opts['exectime_percent'] = $tmp;
            }

            if (
                !empty($options['wrappers'])
                && ($tmp = (int) $options['wrappers']) > 0
            ) {
                $opts['wrappers'] = $tmp;
            }
        }

        ++static::$nInspections;

        try {
            if (!$trace) {
                $output = $this->nspct($subject);
                $this->preface = '[Inspect variable - ' . static::$nInspections . ' - depth:' . $opts['depth']
                    . '|truncate:' . $opts['truncate'] . ']';
            }
            else {
                $output = $this->trc(!$back_trace ? $subject : null);
                $this->preface = '[Inspect trace - ' . static::$nInspections . ' - limit:' . $opts['limit']
                    . '|depth:' . $opts['depth'] . '|truncate:' . $opts['truncate'] . ']';
            }
            if ($opts['code']) {
                $this->code = $opts['code'];
            }
            if ($this->code) {
                $this->preface .= static::FORMAT['newline'] . 'Code: ' . $this->code;
            }
            $this->fileLine = $this->fileLine();
            $this->preface .= static::FORMAT['newline'] . '@' . $this->fileLine;
            if ($this->warnings) {
                $this->preface .= static::FORMAT['newline'] . join(static::FORMAT['newline'], $this->warnings);
            }

            $len_preface = strlen($this->preface);
            $len_output = strlen($output);
            if ($len_output + $len_preface + static::OUTPUT_MARGIN > $opts['output_max']) {
                $output = $this->unicode->truncateToByteLength(
                    $output,
                    $opts['output_max'] - $len_preface + static::OUTPUT_MARGIN
                );
                $this->preface .= static::FORMAT['newline'] . 'Truncated.';
            }

            $this->output =& $output;
            $this->length = $len_preface + strlen($output);
        } catch (\Throwable $xc) {
            $file = $xc->getFile();
            try {
                $tmp = str_replace($this->documentRoots(), '[document_root]', $file);
                if ($tmp) {
                    $file = $tmp;
                }
            } catch (\Throwable $ignore) {
            }
            $msg = 'Inspect ' . $kind . ' failure: ' . get_class($xc) . '@' . $file . ':' . $xc->getLine()
                . ': ' . addcslashes($xc->getMessage(), "\0..\37");

            error_log($msg);

            $this->output = $msg;
            $this->length = strlen($msg);
        }
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return (!$this->preface ? '' : (static::FORMAT['newline'] . $this->preface))
            . $this->output;
    }

    /**
     * @return array {
     *      @var string $preface
     *      @var string $output
     *      @var int $length  Byte (ASCII) length.
     *      @var string $fileLine
     *      @var int $code
     * }
     */
    public function get() : array
    {
        return [
            'preface' => $this->preface,
            'output' => $this->output,
            'length' => $this->length,
            'fileLine' => $this->fileLine,
            'code' => $this->code,
        ];
    }

    /**
     * @return bool
     */
    public function exceedsLength() : bool {
        if ($this->length > $this->options['output_max']) {
            $this->abort = true;
            if ($kind = 'variable') {
                $this->warnings[] = 'Variable inspection aborted - output length ' . $this->length
                    . ' exceeds output_max ' . $this->options['output_max'] . '.'
                    . ' Using depth ' . $this->options['depth'] . ' and truncate ' . $this->options['truncate'] . '.'
                    . ' Try less depth or truncate value.';
            }
            else {
                $this->warnings[] = 'Trace inspection aborted - output length ' . $this->length
                    . ' exceeds output_max ' . $this->options['output_max'] . '.'
                    . ' Using limit ' . $this->options['limit'] . ', depth ' . $this->options['depth']
                    . ' and truncate ' . $this->options['truncate'] . '.'
                    . ' Try less limit, depth or truncate value.';
            }
            return true;
        }
        return false;
    }

    /**
     * @var int
     */
    protected static $requestTime = -1;

    /**
     * Check if time exceeds request start time plus
     * option 'exectime_percent'/EXEC_TIMEOUT_DEFAULT
     * percent of max_execution_time.
     *
     * @return bool
     */
    public function exceedsTime() : bool {
        $started = static::$requestTime;
        if ($started < 1) {
            // Zero means none; cli mode.
            if (!$started) {
                return false;
            }
            if (empty($_SERVER['REQUEST_TIME'])) {
                // None; cli mode.
                static::$requestTime = 0;
                return false;
            }
            static::$requestTime = $started = (int) $_SERVER['REQUEST_TIME'];
        }
        $timeout = (int) ini_get('max_execution_time');
        if (!$timeout) {
            // Cli mode anyway.
            static::$requestTime = 0;
            return false;
        }
        if (time() > $started + ($timeout * $this->options['exectime_percent'] / 100)) {
            if ($kind = 'variable') {
                $this->warnings[] = 'Variable inspection aborted after ' . $this->options['exectime_percent']
                    . '% of PHP max_execution_time ' . $timeout . ' passed,'
                    . ' using depth ' . $this->options['depth'] . '.'
                    . ' Try less depth.';
            }
            else {
                $this->warnings[] = 'Trace inspection aborted after ' . $this->options['exectime_percent']
                    . '% of PHP max_execution_time ' . $timeout . ' passed,'
                    . ' using limit ' . $this->options['limit'] . ' and depth ' . $this->options['depth'] . '.'
                    . ' Try less limit or depth.';
            }
            return true;
        }
        return false;
    }

    /**
     * @var int
     */
    protected static $nInspections = 0;

    /**
     * @throws LogicException
     *      Failing recursion depth control.
     *
     * @param mixed $subject
     * @param int $depth
     *
     * @return string
     */
    protected function nspct($subject, $depth = 0) : string {
        if ($this->abort) {
            return '';
        }

        ++$this->nspctCall;
        $depth_max = $this->options['depth'];

        if ($depth > $depth_max) {
            throw new LogicException('Algo errror, depth exceeded.');
        }
        // Check length every time.
        if ($this->exceedsLength()) {
            return '';
        }
        // Check execution time every 1000th time.
        if (!((++$this->nspctCall) % 1000) && $this->exceedsTime()) {
            return '';
        }

        // Paranoid.
        $output = '';

        // Object or array.
        $is_array = $is_num_array = false;
        if (is_object($subject) || ($is_array = is_array($subject))) {
            if ($is_array) {
                $output = '(array:';
                $n_elements = count($subject);
            } else {
                $output = '(' . get_class($subject) . ':';
                if ($subject instanceof \Countable && $subject instanceof \Traversable) {
                    // Require Traversable too, because otherwise the count
                    // may not reflect a foreach.
                    $n_elements = count($subject);
                } else {
                    $n_elements = count(get_object_vars($subject));
                }
            }
            // If at max depth: simply get length of the container.
            if ($depth == $depth_max) {
                if (!$n_elements) {
                    $output .= $n_elements . ')';
                } elseif ($is_array) {
                    $output .= $n_elements . ') [...]';
                } else {
                    $output .= $n_elements . ') {...}';

                }

                $this->length += strlen($output);
                return $output;
            }
            // Dive into container buckets.
            else {
                // Numberically indexed array?
                if ($is_array && ctype_digit(join('', array_keys($subject)))) {
                    $is_num_array = true;
                    $output .= $n_elements . ') [';
                }
                else {
                    $output .= $n_elements . ') {';
                }

                // @todo: do we actually need more delimiters; old Inspect had other formats for 'later' and 'last'.
                $delim_first = static::FORMAT['delimiter'] . str_repeat(static::FORMAT['indent'], $depth + 1);
                $delim_middle = static::FORMAT['delimiter'] . str_repeat(static::FORMAT['indent'], $depth + 1);
                $delim_end = static::FORMAT['delimiter'] . str_repeat(static::FORMAT['indent'], $depth);

                $any_skip_keys = !!$this->options['skip_keys'];
                $i = -1;
                foreach ($subject as $key => &$element) {
                    ++$i;
                    if ($i) {
                        $output .= $delim_middle;
                    }
                    else {
                        $output .= $delim_first;
                    }
                    if (
                        $is_array && !$is_num_array && $key === 'GLOBALS'
                        && is_array($element) && array_key_exists('GLOBALS', $element)
                    ) {
                        $output .= 'GLOBALS: (array) *RECURSION*';
                    }
                    elseif ($any_skip_keys && in_array($key, $this->options['skip_keys'], true)) {
                        $output .= $key . ': F';
                    }
                    elseif (!$is_num_array && in_array($key, static::HIDE_VALUE_OF_KEYS) && is_string($element)) {
                        $len_bytes = strlen($element);
                        if (!$len_bytes) {
                            $output .= $key . ': (string:0:0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                        }
                        else {
                            $output .= $key . ': (string:' . $this->unicode->strlen($element) . ':'
                                . $len_bytes . ':0) ' . static::FORMAT['quote'] . '...' . static::FORMAT['quote'];
                        }
                    }
                    else {
                        $output .= $key . ': ' . $this->nspct($element, $depth + 1);
                    }
                }
                unset($element);

                if ($is_num_array) {
                    $output .= $delim_end . ']';
                }
                else {
                    $output .= $delim_end . '}';
                }

                $this->length += strlen($output);
                return $output;
            }
        }

        // Scalars, null and resource.
        $type = gettype($subject);
        switch ($type) {
            case 'boolean':
                $output = '(boolean) ' . ($subject ? 'true' : 'false');
                break;

            case 'integer':
            case 'double':
            case 'float':
                if (!is_finite($subject)) {
                    $output = '(' . (is_nan($subject) ? 'NaN' : 'infinite') . ')';
                } else {
                    $output = '(' . ($type == 'double' ? 'float' : $type) . ') '
                        . (!$subject ? '0' : $this->sanitize->numberToString($subject));
                }
                break;

            case 'string':
                $output = '(string:';
                // (string:n0|n1|n2): n0 ~ multibyte length, n1 ~ ascii length,
                // n2 only occurs if truncation.
                $len_bytes = strlen($subject);
                if (!$len_bytes) {
                    $output = '0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                } elseif (!$this->validate->unicode($subject)) {
                    $output .= '?|' . $len_bytes . '|0) *INVALID_UTF8*';
                } else {
                    $len_unicode = $this->unicode->strlen($subject);
                    $output .= $len_unicode . ':' . $len_bytes;
                    $truncate = $this->options['truncate'];
                    if (!$truncate) {
                        $output .= ':0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                    } else {
                        $trunced_to = 0;
                        if ($len_unicode > $truncate) {
                            // Long string; shorten before replacing.
                            $subject = $this->unicode->substr($subject, 0, $truncate);
                            $trunced_to = $truncate;
                        }
                        // Remove document root(s).
                        if (
                            strpos($subject, '/') !== false
                            || (DIRECTORY_SEPARATOR == '\\' && strpos($subject, '\\') !== false)
                        ) {
                            $subject = str_replace($this->documentRoots(), '[document_root]', $subject);
                        }
                        // Replace listed neeedles with harmless symbols.
                        $subject = str_replace($this->options['needles'], $this->options['replacers'], $subject);
                        // Escape lower ASCIIs.
                        $subject = addcslashes($subject, "\0..\37");
                        // Escape HTML entities.
                        $subject = htmlspecialchars(
                            $subject,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8',
                            false
                        );
                        // Re-truncate, in case it's gotten longer.
                        if ($this->unicode->strlen($subject) > $truncate) {
                            $subject = $this->unicode->substr($subject, 0, $truncate);
                            $trunced_to = $truncate;
                        }

                        if ($trunced_to) {
                            $output .= ':' . $trunced_to;
                        }
                        $output .= ') ' . static::FORMAT['quote'] . $subject . static::FORMAT['quote'];
                    }
                }
                break;

            case 'resource':
                $output = '(resource) ' . get_resource_type($subject);
                break;

            case 'NULL':
                $output = '(null)';
                break;

            default:
                // Unknown.
                $output = '(' . $type . ')';
        }

        $this->length += strlen($output);
        return $output;
    }

    /**
     * @param \Throwable|null $throwableOrNull
     *
     * @return string
     */
    protected function trc($throwableOrNull) : string {
        return '';
    }

    /**
     * @var array
     */
    static $documentRoots = [];

    /**
     * Expects current working dir to be document root.
     *
     * Returns two paths if document root seems symlinked.
     * Returns up to four paths if OS is Windows(-like).
     *
     * @return string[]
     */
    public function documentRoots() : array {
        $paths = static::$documentRoots;
        if (!$paths) {
            $paths[] = $real_path = getcwd();
            $symlinked_path = dirname($_SERVER['SCRIPT_FILENAME']);
            if ($symlinked_path != $real_path) {
                // The symlink must be a subset of the real path, so for
                // replacers it works swell with symlink after real path.
                $paths[] = $symlinked_path;
            }
            if (DIRECTORY_SEPARATOR == '\\') {
                $forward_slash_path = str_replace('\\', '/', $real_path);
                if ($forward_slash_path != $real_path) {
                    $paths[] = $forward_slash_path;
                }
                if ($symlinked_path != $real_path) {
                    $forward_slash_path = str_replace('\\', '/', $symlinked_path);
                    if ($forward_slash_path != $symlinked_path) {
                        $paths[] = $forward_slash_path;
                    }
                }
            }
            static::$documentRoots = $paths;
        }
        return $paths;
    }

    /**
     * Get file and line of outmost call to an inspect function or public method.
     *
     * Replaces document root with [document_root].
     *
     * @return string
     *      Empty if no success.
     */
    protected function fileLine() {
        $trace = debug_backtrace();
        // Find first frame whose file isn't named like our library files.
        $le = count($trace);
        $i_frame = -1;
        for ($i = 1; $i < $le; ++$i) {
            if (
                !empty($trace[$i]['file'])
                && !in_array(basename($trace[$i]['file']), static::LIB_FILENAMES)
            ) {
                $i_frame = $i;
                break;
            }
        }
        if (
            $i_frame > -1
            && (!$this->options['wrappers'] || !empty($trace[$i_frame += $this->options['wrappers']]['file']))
        ) {
            return str_replace($this->documentRoots(), '[document_root]', $trace[$i_frame]['file'])
                . ':' . (isset($trace[$i_frame]['line']) ? $trace[$i_frame]['line'] : '?');
        }
        return '';
    }

    /**
     * Get config var.
     *
     * If Inspect was provided with a config object, that will be used.
     * Otherwise this implementation uses environment vars.
     *
     *  Vars, and their effective defaults:
     *  - (int) trace_limit:        5 (TRACE_LIMIT_DEFAULT)
     *  - (int) truncate:           1000 (TRUNCATE_DEFAULT)
     *  - (int) output_max:         1Mb (OUTPUT_DEFAULT)
     *  - (int) exectime_percent:   90 (EXEC_TIMEOUT_DEFAULT)
     *
     * Config object var names will be prefixed by
     * CONFIG_DOMAIN . CONFIG_DELIMITER
     * Environment var names will be prefixed by CONFIG_DOMAIN; example
     * lib_simplecomplex_jsonlog_siteid.
     * Beware that environment variables are always strings.
     *
     * @param string $domain
     *      Default: static::CONFIG_DOMAIN.
     * @param string $name
     * @param mixed $default
     *      Default: null.
     *
     * @return mixed
     *      String, unless no such var and arg default isn't string.
     */
    public function configGet($domain, $name, $default = null) : mixed
    {
        if ($this->config) {
            return $this->config->get(
                ($domain ? $domain : static::CONFIG_DOMAIN) . static::CONFIG_DELIMITER . $name,
                $default
            );
        }
        return ($val = getenv(($domain ? $domain : static::CONFIG_DOMAIN) . '_' . $name)) !== false ? $val : $default;
    }

    /**
     * Unless Inspect was provided with a config object, this implementation
     * does nothing, since you can't save an environment var.
     *
     * @param string $domain
     * @param string $name
     * @param mixed $value
     *
     * @return bool
     */
    public function configSet($domain, $name, $value) : bool
    {
        if ($this->config) {
            return $this->config->set(
                ($domain ? $domain : static::CONFIG_DOMAIN) . static::CONFIG_DELIMITER . $name,
                $value
            );
        }
        return putenv(($domain ? $domain : static::CONFIG_DOMAIN) . '_' . $name . '=' . $value);
    }
}
