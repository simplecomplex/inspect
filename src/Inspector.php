<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

/**
 * Variable analyzer and exception tracer.
 *
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
    const CONFIG_SECTION = 'lib_simplecomplex_inspect';

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
     * Maximum (longest) string truncation; multibyte (Unicode) length.
     *
     * @var int
     */
    const TRUNCATE_MAX = 100000;

    /**
     * Default string truncation; multibyte (Unicode) length.
     *
     * @var int
     */
    const TRUNCATE_DEFAULT = 1000;

    /**
     * Whether to escape HTML in strings.
     *
     * @var bool
     */
    const ESCAPE_HTML = false;

    /**
     * Absolute maximum byte (ASCII) length of an inspection/trace output.
     *
     * @var int
     */
    const OUTPUT_MAX = 2097152;

    /**
     * Default maximum byte (ASCII) length of an inspection/trace output.
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
        'trace_spacer' => '- - - - - - - - - - - - - - - - - - - - - - - - -',
        'enclose_tag' => 'pre',
    ];

    /**
     * @var string[]
     */
    const LIB_FILENAMES = [
        'Inspect.php',
        'Inspector.php',
    ];

    /**
     * Options, and their defaults:
     * - (int) depth: max object/array recursion; DEPTH_DEFAULT/TRACE_DEPTH_DEFAULT
     * - (int) limit: max trace frame; TRACE_LIMIT_DEFAULT
     * - (int) code: error code, overrides exception code; none
     * - (int) truncate: truncate strings; TRUNCATE_DEFAULT
     * - (arr) skip_keys: skip those object/array keys; none
     * - (arr) needles: replace in strings; NEEDLES
     * - (arr) replacers: replace in strings; REPLACERS
     * - (bool) escape_html: replace in strings; ESCAPE_HTML
     * - (int) output_max: replace in strings; OUTPUT_DEFAULT
     * - (int) exectime_percent: replace in strings; EXEC_TIMEOUT_DEFAULT
     * - (int) wrappers: number of wrapping functions/methods, to be hidden; zero
     * - (str) kind: (auto) trace when subject is \Throwable, otherwise variable
     *
     * @var array
     */
    protected $options = array(
        'depth' => 0,
        'limit' => 0,
        'code' => 0,
        'truncate' => 0,
        'skip_keys' => [],
        'needles' => [],
        'replacers' => [],
        'escape_html' => false,
        'output_max' => 0,
        'exectime_percent' => 0,
        'wrappers' => 0,
        'kind' => '',
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


    // Constructor.-------------------------------------------------------------

    /**
     * @var Inspect
     */
    protected $proxy;

    /**
     * Do not call this directly, use Inspect instead.
     *
     * @see Inspect::__construct()
     * @see Inspect::getInstance()
     *
     * @internal
     *
     * @param Inspect $proxy
     * @param mixed $subject
     * @param array|int|string $options
     *   Integer when inspecting variable: maximum depth.
     *   Integer when tracing: stack frame limit.
     *   String: kind (variable|trace); otherwise ignored.
     *   Not array|integer|string: ignored.
     */
    public function __construct(Inspect $proxy, $subject, $options = [])
    {
        $this->proxy = $proxy;

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
                    if ($options > 0) {
                        $depth_or_limit = $options;
                    }
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
        $this->kind = $kind;

        // Prepare options.-----------------------------------------------------
        $opts =& $this->options;
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
            // Doesn't use ??-operator ~ casting class var to int is redundant.
            $opts['limit'] = ($tmp = $this->proxy->config->get(static::CONFIG_SECTION, 'trace_limit')) ?
                (int) $tmp : static::TRACE_LIMIT_DEFAULT;
        }
        // truncate.
        // Doesn't use ??-operator ~ casting class var to int is redundant.
        $opts['truncate'] = ($tmp = $this->proxy->config->get(static::CONFIG_SECTION, 'truncate')) ?
            (int) $tmp : static::TRUNCATE_DEFAULT;
        // escape_html.
        $opts['escape_html'] = $this->proxy->config->get(static::CONFIG_SECTION, 'escape_html', static::ESCAPE_HTML);
        // skip_keys.
        // Keep default: empty array.
        // replacers.
        $opts['replacers'] = static::REPLACERS;
        // needles; only arg options override if arg options replacers.
        $opts['needles'] = static::NEEDLES;
        // output_max.
        // Doesn't use ??-operator ~ casting class var to int is redundant.
        $opts['output_max'] = ($tmp = $this->proxy->config->get(static::CONFIG_SECTION, 'output_max')) ?
            (int) $tmp : static::OUTPUT_DEFAULT;
        // exectime_percent.
        // Doesn't use ??-operator ~ casting class var to int is redundant.
        $opts['exectime_percent'] = ($tmp = $this->proxy->config->get(static::CONFIG_SECTION, 'exectime_percent')) ?
            (int) $tmp : static::EXEC_TIMEOUT_DEFAULT;
        // wrappers.
        // Keep default: zero.

        // Overriding options by argument.--------------------------------------
        if ($use_arg_options) {
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
                $trace && !empty($options['limit'])
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

            if (isset($options['escape_html'])) {
                $opts['escape_html'] = !!$options['escape_html'];
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

        if (!static::$nInspections) {
            /**
             * Init this; used a lot.
             *
             * @see Inspector::$documentRoots
             * @see Inspector::$documentRootLength
             */
            $this->documentRoots();
        }
        ++static::$nInspections;

        try {
            if (!$trace) {
                $output = $this->nspct($subject);
            } else {
                $output = $this->trc(!$back_trace ? $subject : null);
            }
            // Don't enclose in tag in cli mode.
            if (static::FORMAT['enclose_tag'] && PHP_SAPI != 'cli') {
                $output = '<' . static::FORMAT['enclose_tag']
                    . ' class="simplecomplex-inspect inspect-' . $kind . '">'
                    . $output . '</' . static::FORMAT['enclose_tag'] . '>';
            }

            if ($opts['code']) {
                $this->code = $opts['code'];
            }
            $this->fileLine = $this->fileLine();
            $this->preface = $this->preface();

            $len_preface = strlen($this->preface);
            $len_output = strlen($output);
            if ($len_output + $len_preface + static::OUTPUT_MARGIN > $opts['output_max']) {
                $output = $this->proxy->unicode->truncateToByteLength(
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
                $tmp = str_replace(static::$documentRoots, '[document_root]', $file);
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
        return (!$this->preface ? '' : ($this->preface . static::FORMAT['newline']))
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
    public function exceedsLength() : bool
    {
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
    public function exceedsTime() : bool
    {
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
     * @param mixed $subject
     * @param int $depth
     *
     * @return string
     *
     * @throws \LogicException
     *      Failing recursion depth control.
     */
    protected function nspct($subject, $depth = 0) : string
    {
        if ($this->abort) {
            return '';
        }

        ++$this->nspctCall;
        $depth_max = $this->options['depth'];

        if ($depth > $depth_max) {
            throw new \LogicException('Algo errror, depth exceeded.');
        }
        // Check length every time.
        if ($this->exceedsLength()) {
            return '';
        }
        // Check execution time every 1000th time.
        if (!((++$this->nspctCall) % 1000) && $this->exceedsTime()) {
            return '';
        }

        // Object or array.
        $is_array = $is_num_array = $is_num_array_access = false;
        if (is_object($subject) || ($is_array = is_array($subject))) {
            if ($is_array) {
                $output = '(array:';
                $n_elements = count($subject);
                // Numberically indexed array?
                if (!$n_elements || ctype_digit(join('', array_keys($subject)))) {
                    $is_num_array = true;
                }
            } else {
                $output = '(' . get_class($subject) . ':';
                if ($subject instanceof \Countable && $subject instanceof \Traversable) {
                    // Require Traversable too, because otherwise the count
                    // may not reflect a foreach.
                    $n_elements = count($subject);
                    if ($subject instanceof \ArrayAccess) {
                        if (!$n_elements) {
                            $is_num_array_access = true;
                        } elseif (
                            ($subject instanceof \ArrayObject || $subject instanceof \ArrayIterator)
                            && ctype_digit(join('', array_keys($subject->getArrayCopy())))
                        ) {
                            $is_num_array_access = true;
                        }
                    }
                } else {
                    $n_elements = count(get_object_vars($subject));
                }
            }
            $output .= $n_elements . ') ';
            if (!$n_elements) {
                $output .= $is_num_array || $is_num_array_access ? '[]' : '{}';

                $this->length += strlen($output);
                return $output;
            }
            // If at max depth: simply get length of the container.
            if ($depth == $depth_max) {
                if ($is_num_array || $is_num_array_access) {
                    $output .= '[...]';
                } else {
                    $output .= '{...}';
                }

                $this->length += strlen($output);
                return $output;
            }
            // Dive into container buckets.
            else {
                if ($is_num_array || $is_num_array_access) {
                    $output .= '[';
                }
                else {
                    $output .= '{';
                }

                $delim_first = $delim_middle =
                    static::FORMAT['delimiter'] . str_repeat(static::FORMAT['indent'], $depth + 1);
                $delim_end = static::FORMAT['delimiter'] . str_repeat(static::FORMAT['indent'], $depth);

                $any_skip_keys = !!$this->options['skip_keys'];
                $i = -1;
                foreach ($subject as $key => $element) {
                    ++$i;
                    $output .= $i ? $delim_middle : $delim_first;
                    if (
                        $is_array && !$is_num_array && $key === 'GLOBALS'
                        && is_array($element) && array_key_exists('GLOBALS', $element)
                    ) {
                        $output .= 'GLOBALS: (array) *RECURSION*';
                    }
                    elseif ($any_skip_keys && in_array($key, $this->options['skip_keys'], true)) {
                        $output .= $key . ': F';
                    }
                    elseif (
                        !$is_num_array && !$is_num_array_access && in_array($key, static::HIDE_VALUE_OF_KEYS, true)
                    ) {
                        $len_bytes = strlen($element);
                        if (!$len_bytes) {
                            $output .= $key . ': (string:0:0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                        }
                        else {
                            $output .= $key . ': (string:' . $this->proxy->unicode->strlen($element) . ':'
                                . $len_bytes . ':0) ' . static::FORMAT['quote'] . '...' . static::FORMAT['quote'];
                        }
                    }
                    else {
                        $output .= $key . ': ' . $this->nspct($element, $depth + 1);
                    }
                }

                if ($is_num_array || $is_num_array_access) {
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
                        . (!$subject ? '0' : $this->proxy->sanitize->numberToString($subject));
                }
                break;

            case 'string':
                $output = '(string:';
                // (string:n0|n1|n2): n0 ~ multibyte length, n1 ~ ascii length,
                // n2 only occurs if truncation.
                $len_bytes = strlen($subject);
                if (!$len_bytes) {
                    $output .= '0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                } elseif (!$this->proxy->validate->unicode($subject)) {
                    $output .= '?|' . $len_bytes . '|0) *INVALID_UTF8*';
                } else {
                    $len_unicode = $this->proxy->unicode->strlen($subject);
                    $output .= $len_unicode . ':' . $len_bytes;
                    $truncate = $this->options['truncate'];
                    if (!$truncate) {
                        $output .= ':0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                    } else {
                        $trunced_to = 0;
                        // Replace document root?
                        $docroot_length = static::$documentRootLength;
                        if (
                            $len_bytes >= $docroot_length
                            && (
                                strpos($subject, '/') !== false
                                || (DIRECTORY_SEPARATOR == '\\' && strpos($subject, '\\') !== false)
                            )
                        ) {
                            $docroot_replace = true;
                        } else {
                            $docroot_replace = false;
                        }
                        // Long string; shorten before replacing.
                        if ($len_unicode > $truncate) {
                            // Replace document root before truncation?
                            if ($docroot_replace && $truncate < $docroot_length) {
                                $docroot_replace = false;
                                $subject = str_replace(static::$documentRoots, '[document_root]', $subject);
                            }
                            $subject = $this->proxy->unicode->substr($subject, 0, $truncate);
                            $trunced_to = $truncate;
                        }
                        // Remove document root after truncation.
                        if ($docroot_replace) {
                            $subject = str_replace(static::$documentRoots, '[document_root]', $subject);
                        }
                        // Replace listed needles with harmless symbols.
                        $subject = str_replace($this->options['needles'], $this->options['replacers'], $subject);
                        // Escape lower ASCIIs.
                        $subject = addcslashes($subject, "\0..\37");
                        // Escape HTML entities.
                        if ($this->options['escape_html']) {
                            $subject = htmlspecialchars(
                                $subject,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8',
                                false
                            );
                        }
                        // Re-truncate, in case subject's gotten longer.
                        if ($this->proxy->unicode->strlen($subject) > $truncate) {
                            $subject = $this->proxy->unicode->substr($subject, 0, $truncate);
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
     *
     * @throws \TypeError
     *      Arg throwableOrNull not \Throwable or null.
     */
    protected function trc(/*?\Throwable*/ $throwableOrNull) : string
    {
        // Received Throwable, by arg.
        if ($throwableOrNull) {
            if ($throwableOrNull instanceof \Throwable) {
                $thrwbl_class = get_class($throwableOrNull);
                if (!$this->code) {
                    $this->code = $throwableOrNull->getCode();
                }
                $trace = $throwableOrNull->getTrace();
                if (count($trace) > $this->options['limit']) {
                    array_splice($trace, $this->options['limit']);
                }
            }
            else {
                throw new \TypeError(
                    'Arg throwableOrNull type['
                    . (!is_object($throwableOrNull) ? gettype($throwableOrNull) : get_class($throwableOrNull))
                    . '] is not Throwable or null.'
                );
            }
        }
        // Create trace, when none given by arg.
        else {
            $thrwbl_class = NULL;
            $trace = debug_backtrace();
            // Remove top levels on synthetic trace, first of all this method.
            array_shift($trace);
            // Find first frame whose file isn't named like our library files.
            $le = count($trace);
            $i_frame = -1;
            for ($i = 0; $i < $le; ++$i) {
                if (
                    !empty($trace[$i]['file'])
                    && !in_array(basename($trace[$i]['file']), static::LIB_FILENAMES)
                ) {
                    $i_frame = $i;
                    break;
                }
            }
            if ($i_frame > -1) {
                $trace = array_slice($trace, $i_frame);
            }
            // Enforce the trace limit.
            if (count($trace) > $this->options['limit']) {
                // Plus one because we need the bucket holding the initial event.
                array_splice($trace, $this->options['limit'] + 1);
            }
        }

        $delim = static::FORMAT['delimiter'];

        // If exception: resolve its origin and render code and message.
        if ($thrwbl_class) {
            $output = 'Throwable (' . $thrwbl_class . ') - code: ' . $throwableOrNull->getCode()
                . $delim . '@' . str_replace(static::$documentRoots, '[document_root]', $throwableOrNull->getFile())
                . ':' . $throwableOrNull->getLine()
                . $delim . 'message: '
                . addcslashes(
                    str_replace(static::$documentRoots, '[document_root]', $throwableOrNull->getMessage()),
                    "\0..\37"
                );
        } else {
            $output = 'Backtrace';
        }

        // Iterate stack frames.
        $i_frame = -1;
        foreach ($trace as $frame) {
            $output .= $delim . (++$i_frame) . ' ' . static::FORMAT['trace_spacer'];
            if (isset($frame['file'])) {
                $output .= $delim . '@' . str_replace(static::$documentRoots, '[document_root]', $frame['file'])
                    . ':' . (isset($frame['line']) ? $frame['line'] : '?');
            } else {
                $output .= $delim . '@unknown';
            }

            $function = !empty($frame['function']) ? $frame['function'] : '';
            $class = !empty($frame['class']) ? $frame['class'] : '';
            $type = !empty($frame['type']) ? $frame['type'] : '';

            if (isset($frame['object'])) {
                if (!$class) {
                    $class = get_class($frame['object']);
                }
                if ($function) {
                    $output .= $delim . 'method: (' . $class . ')' . ($type ? $type : '->') . $function;
                } else {
                    // Probably not possible.
                    $output .= $delim . 'object (' . $class . ')';
                }
            } elseif ($function) {
                if (!$class) {
                    $output .= $delim . 'function: ' . $function;
                } else {
                    $output .= $delim . 'static method: ' . $class . ($type ? $type : '::') . $function;
                }
            }

            // Args.
            if (isset($frame['args'])) {
                $le = count($frame['args']);
                $output .= $delim . 'args (' . $le . ')' . (!$le ? '' : ':');
                for ($i = 0; $i < $le; ++$i) {
                    $output .= $delim . $this->nspct($frame['args'][$i]);
                }
            }
        }

        return $output;
    }

    /**
     * @var array
     */
    static $documentRoots = [];

    /**
     * @var int
     */
    static $documentRootLength = 0;

    /**
     * Expects current working dir to be document root.
     *
     * Returns two paths if document root seems symlinked.
     * Returns up to four paths if OS is Windows(-like).
     *
     * @return string[]
     */
    public function documentRoots() : array
    {
        $paths = static::$documentRoots;
        if (!$paths) {
            $paths[] = $real_path = getcwd();
            static::$documentRootLength = strlen($real_path);
            $symlinked_path = dirname($_SERVER['SCRIPT_FILENAME']);
            // In cli mode a symlinked path is empty, because SCRIPT_FILENAME
            // is the filename only; no path.
            if (
                $symlinked_path && $symlinked_path != '.'
                && $symlinked_path != $real_path
            ) {
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
    protected function fileLine() : string
    {
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
            return str_replace(static::$documentRoots, '[document_root]', $trace[$i_frame]['file'])
                . ':' . (isset($trace[$i_frame]['line']) ? $trace[$i_frame]['line'] : '?');
        }
        return '';
    }

    /**
     * @return string
     */
    protected function preface() : string
    {
        if ($this->kind == 'variable') {
            $preface = '[Inspect variable - #' . static::$nInspections . ' - depth:' . $this->options['depth']
                . '|truncate:' . $this->options['truncate'] . ']';
        }
        else {
            $preface = '[Inspect trace - #' . static::$nInspections . ' - limit:' . $this->options['limit']
                . '|depth:' . $this->options['depth'] . '|truncate:' . $this->options['truncate'] . ']';
        }
        $preface .= '@' . $this->fileLine;
        if ($this->code) {
            $preface .= static::FORMAT['newline'] . 'Code: ' . $this->code;
        }
        if ($this->warnings) {
            $preface .= static::FORMAT['newline'] . join(static::FORMAT['newline'], $this->warnings);
        }
        return $preface;
    }
}
