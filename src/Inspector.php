<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2023 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

/**
 * Produces variable inspection or backtrace,
 * stringable and loggable.
 *
 * @internal  Allowed for InspectInterface only.
 * @see InspectInterface
 *
 * @package SimpleComplex\Inspect
 */
class Inspector implements InspectorInterface
{
    /**
     * Maximum sub var recursion depth.
     *
     * @var int
     */
    const DEPTH_MAX = 20;

    /**
     * Default sub var recursion depth.
     *
     * @see Helper\Config::$depth
     * @see Inspector::$options['depth']
     *
     * @var int
     */
    const DEPTH_DEFAULT = 10;

    /**
     * Default sub var recursion depth, when tracer inspects
     * function/method arguments.
     *
     * @see Inspector::$options['depth']
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
     * @see Helper\Config::$trace_limit
     * @see Inspector::$options['limit']
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
     * @see Helper\Config::$truncate
     * @see Inspector::$options['truncate']
     *
     * @var int
     */
    const TRUNCATE_DEFAULT = 1000;

    /**
     * Whether to escape HTML in strings.
     *
     * @see Helper\Config::$escape_html
     * @see Inspector::$options['escape_html']
     *
     * @var bool
     */
    const ESCAPE_HTML = false;

    /**
     * Absolute maximum byte (ASCII) length of an inspection/trace output.
     *
     * A higher value than ~2mb will collide with MySQL default maximum
     * query length (max_allowed_packet).
     *
     * @var int
     */
    const OUTPUT_MAX = 2097152;

    /**
     * Default maximum byte (ASCII) length of an inspection/trace output.
     *
     * @see Helper\Config::$output_max
     * @see Inspector::$options['output_max']
     * @see Inspector::exceedsLength()
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
     * @var int
     */
    const EXEC_TIMEOUT_MAX = 95;

    /**
     * Percentage of max_execution_time.
     *
     * @see Helper\Config::$exectime_percent
     * @see Inspector::$options['exectime_percent']
     * @see Inspector::exceedsTime()
     *
     * @var int
     */
    const EXEC_TIMEOUT_DEFAULT = 90;

    /**
     * @see Helper\Config::$rootdir_replace
     * @see Inspector::$options['rootdir_replace']
     * @see Inspect::rootDirReplace()
     *
     * @var bool
     */
    const ROOT_DIR_REPLACE = true;

    /**
     * String variable str_replace() needles.
     *
     * @see Inspector::$options['needles']
     *
     * @var string[]
     */
    const NEEDLES = [
        "\0", "\1", "\n", "\r", "\t", '"', "'",
    ];

    /**
     * String variable str_replace() replacers.
     *
     * @see Inspector::$options['replacers']
     *
     * @var string[]
     */
    const REPLACERS = [
        '_NUL_', '_SOH_', '_NL_', '_CR_', '_TB_', '”', '’',
    ];

    /**
     * String variable str_replace() needles when option 'escape_html'.
     *
     * @see Inspector::$options['needles']
     *
     * @var string[]
     */
    const NEEDLES_ESCAPE_HTML = [
        "\0", "\1", "\n", "\r", "\t", '<', '>', '"', "'",
    ];

    /**
     * String variable str_replace() replacers when option 'escape_html'.
     *
     * @see Inspector::$options['replacers']
     *
     * @var string[]
     */
    const REPLACERS_ESCAPE_HTML = [
        '_NUL_', '_SOH_', '_NL_', '_CR_', '_TB_', '&lt;', '&gt;', '&quot;', '&apos;',
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
        'trace_spacer' => '- - - - - - - - - - - - - - - - - - - -',
        'trace_end' => '------------------------------------ #',
        // Otherwise use 'pre'.
        'enclose_tag' => '',
    ];

    /**
     * Keys are file names, values are irrelevant.
     * Allows a child class to extend parent's list by doing
     * const LIB_FILENAMES = [
     *   'SomeInspector.php' => null,
     * ] + ParentClass::LIB_FILENAMES;
     *
     * @var mixed[]
     */
    const LIB_FILENAMES = [
        'Inspect.php' => null,
        'Inspector.php' => null,
    ];

    /**
     * Options, and their defaults:
     * - (int) depth: max object/array recursion; DEPTH_DEFAULT/TRACE_DEPTH_DEFAULT
     * - (int) limit: max trace frame; TRACE_LIMIT_DEFAULT
     * - (int) code: error code, overrides exception code; none
     * - (int) truncate: string truncation; TRUNCATE_DEFAULT
     * - (arr) skip_keys: skip those object/array keys; none
     * - (bool) escape_html: replace in strings; ESCAPE_HTML
     * - (arr) needles: replace in strings; NEEDLES/NEEDLES_ESCAPE_HTML
     * - (arr) replacers: replace in strings; REPLACERS/REPLACERS_ESCAPE_HTML
     * - (int) output_max: replace in strings; OUTPUT_DEFAULT
     * - (int) exectime_percent: replace in strings; EXEC_TIMEOUT_DEFAULT
     * - (bool) rootdir_replace: replace root dir in strings; ROOT_DIR_REPLACE
     * - (int) wrappers: number of wrapping functions/methods, to be hidden; zero
     *
     * @var array
     */
    protected array $options = [
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
        'rootdir_replace' => true,
        'wrappers' => 0,
    ];

    /**
     * @var string
     *  Values: variable|trace.
     */
    protected string $kind = 'variable';


    // Control vars.------------------------------------------------------------

    protected bool $abort = false;

    protected array $warnings = [];

    protected int $nspctCall = 0;

    /**
     * Unlike Inspect ditto this is zero when root dir shan't be replaced,
     * not negative.
     *
     * @var int
     */
    protected int $rootDirLength = 0;


    // Output properties.-------------------------------------------------------

    protected string $preface = '';

    protected string $output = '';

    protected int $length = 0;

    protected string $fileLine = '';

    protected int $code = 0;


    // Constructor.-------------------------------------------------------------

    /**
     * @var InspectInterface
     */
    protected InspectInterface $proxy;

    /**
     * Produces variable inspection or backtrace.
     *
     * @see Inspect::__construct()
     * @see Inspect::getInstance()
     *
     * @internal  Allowed for InspectInterface only.
     *
     * @see Inspector::$options
     *
     * @param InspectInterface $proxy
     * @param mixed $subject
     * @param array|int $options
     *   Integer: maximum depth.
     *   Not array|int: ignored.
     * @param bool $trace
     */
    public function __construct(InspectInterface $proxy, mixed $subject, array|int $options = [], bool $trace = false)
    {
        $this->proxy = $proxy;

        if ($trace) {
            $this->kind = 'trace';
        }
        $opt_depth = -1;
        $arg_opts = false;
        if (is_array($options)) {
            if (isset($options['depth']) && is_int($options['depth'])) {
                $opt_depth = $options['depth'];
            }
            unset($options['depth']);
            if ($options) {
                $arg_opts = true;
            }
        }
        elseif (is_int($options)) {
            $opt_depth = $options;
        }
        // Else ignore.

        // Prepare options.-----------------------------------------------------
        $opts =& $this->options;

        // depth.
        if ($opt_depth > -1 && $opt_depth <= static::DEPTH_MAX) {
            // Minimum depth is 1 when inspecting, 0 when tracing.
            $opts['depth'] = $opt_depth || $trace ? $opt_depth : 1;
        }
        else {
            $opts['depth'] = !$trace ? ($this->proxy->getConfig()->depth ?? static::DEPTH_DEFAULT) :
                ($this->proxy->getConfig()->trace_depth ?? static::TRACE_DEPTH_DEFAULT);
        }

        // limit.
        if ($trace) {
            if ($arg_opts && isset($options['limit']) && is_int($v = $options['limit'])
                && $v > 0 && $v <= static::TRACE_LIMIT_MAX
            ) {
                $opts['limit'] = $v;
            }
            else {
                $opts['limit'] = $this->proxy->getConfig()->trace_limit ?? static::TRACE_LIMIT_DEFAULT;
            }
        }

        // code.
        if ($arg_opts && !empty($options['code']) && is_int($v = $options['code']) && $v > 0) {
            $opts['code'] = $v;
        }

        // truncate; minimum zero.
        if ($arg_opts && isset($options['truncate']) && is_int($v = $options['truncate'])
            && $v >= 0 && $v <= static::TRUNCATE_MAX
        ) {
            $opts['truncate'] = $v;
        }
        else {
            $opts['truncate'] = $this->proxy->getConfig()->truncate ?? static::TRUNCATE_DEFAULT;
        }

        // skip_keys; default to empty array.
        if ($arg_opts && !empty($options['skip_keys'])) {
            $opts['skip_keys'] = is_array($options['skip_keys']) ? $options['skip_keys'] : [
                // Stringify.
                '' . $options['skip_keys']
            ];
        }

        // escape_html.
        $opts['escape_html'] = isset($options['escape_html']) ? ((bool) $options['escape_html']) :
            ($this->proxy->getConfig()->escape_html ?? static::ESCAPE_HTML);

        // needles/replacers.
        if ($arg_opts && !empty($options['replacers']) && is_array($options['replacers'])) {
            $opts['replacers'] = $options['replacers'];
            // Custom needles requires custom replacers, thus sub condition.
            if (!empty($options['needles']) && is_array($options['needles'])) {
                $opts['needles'] = $options['needles'];
            }
            else {
                $opts['needles'] = !$opts['escape_html'] ? static::NEEDLES : static::NEEDLES_ESCAPE_HTML;
            }
        }
        else {
            $opts['needles'] = !$opts['escape_html'] ? static::NEEDLES : static::NEEDLES_ESCAPE_HTML;
            $opts['replacers'] = !$opts['escape_html'] ? static::REPLACERS : static::REPLACERS_ESCAPE_HTML;
        }

        // output_max; minimim 1.
        if ($arg_opts && isset($options['output_max']) && is_int($v = $options['output_max'])
            && $v > 0 && $v <= static::OUTPUT_MAX
        ) {
            $opts['output_max'] = $v;
        }
        else {
            $opts['output_max'] = $this->proxy->getConfig()->output_max ?? static::OUTPUT_DEFAULT;
        }

        // exectime_percent; minimum 1.
        if ($arg_opts && isset($options['exectime_percent']) && is_int($v = $options['exectime_percent'])
            && $v > 0 && $v <= static::EXEC_TIMEOUT_MAX
        ) {
            $opts['exectime_percent'] = $v;
        }
        else {
            $opts['exectime_percent'] = $this->proxy->getConfig()->exectime_percent ?? static::EXEC_TIMEOUT_DEFAULT;
        }

        // rootdir_replace.
        $opts['rootdir_replace'] = $arg_opts && isset($options['rootdir_replace']) ?
            ((bool) $options['rootdir_replace']) :
            ($this->proxy->getConfig()->rootdir_replace ?? static::ROOT_DIR_REPLACE);

        // wrappers; default to zero.
        if ($arg_opts && isset($options['wrappers']) && is_int($v = $options['wrappers']) && $v > 0) {
            $opts['wrappers'] = $v;
        }

        if ($opts['rootdir_replace']) {
            $this->rootDirLength = $this->proxy->rootDirLength();
            if ($this->rootDirLength < 0) {
                $this->rootDirLength = 0;
            }
        }

        ++static::$nInspections;

        try {
            if (!$trace) {
                $output = $this->nspct($subject);
            }
            else {
                $output = $this->trc($subject instanceof \Throwable ? $subject : null);
            }

            // Don't enclose in tag in cli mode.
            if (static::FORMAT['enclose_tag'] && PHP_SAPI != 'cli') {
                $output = '<' . static::FORMAT['enclose_tag']
                    . ' class="simplecomplex-inspect inspect-' . $this->kind . '">'
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
                $output = $this->proxy->getUnicode()->truncateToByteLength(
                    $output,
                    $opts['output_max'] - $len_preface + static::OUTPUT_MARGIN
                );
                $this->preface .= static::FORMAT['newline'] . 'Truncated.';
            }

            $this->output =& $output;
            $this->length = $len_preface + strlen($output);
        }
        catch (\Throwable $xc) {
            $file = $xc->getFile();
            $msg = $xc->getMessage();
            if ($this->rootDirLength) {
                $file = $this->proxy->rootDirReplace($file, true);
                $msg = $this->proxy->rootDirReplace($msg);
            }
            $msg = 'Inspect ' . $this->kind . ' failure: ' . get_class($xc) . '@' . $file . ':' . $xc->getLine()
                . ': ' . addcslashes($msg, "\0..\37");

            error_log($msg);

            $this->output = $msg;
            $this->length = strlen($msg);
        }
    }

    /**
     * @param bool $noPreface
     *      Skip the [inspect ...@file:N] part.
     *
     * @return string
     */
    public function toString(bool $noPreface = false): string
    {
        return ($noPreface || !$this->preface ? '' : ($this->preface . static::FORMAT['newline']))
            . $this->output;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * List of inspection properties.
     *
     * Available for alternative ways of using the products of an inspection.
     *
     * @param bool $options
     *      True: get options bucket too.
     *
     * @return array {
     *     string $preface
     *     string $output
     *     int $length
     *         Byte (ASCII) length.
     *     string $fileLine
     *     int $code
     * }
     */
    public function toArray(bool $options = false): array
    {
        $a = [
            'preface' => $this->preface,
            'output' => $this->output,
            'length' => $this->length,
            'fileLine' => $this->fileLine,
            'code' => $this->code,
        ];
        if ($options) {
            $a['options'] = $this->options;
        }
        return $a;
    }

    /**
     * This implementation attempts to get PSR logger via SimpleComplex DIC
     * if available, and uses plain error_log() as fallback.
     *
     * Cross-framework dependency/service injection awareness is unfortunately
     * not possible, due to missing standard.
     *
     * Do override this method in accordance with framework (if any).
     *
     * @see error_log()
     *
     * {@inheritDoc}
     */
    public function log($level = 'debug', $message = '', array $context = []): void
    {
        // Leave $level validation to PSR logger (or equivalent).

        /** @var \Psr\Log\LoggerInterface|null $logger */
        $logger = null;
        // Attempt getting via SimpleComplex DIC.
        $class_dependency = '\\SimpleComplex\\Utils\\Dependency';
        if (class_exists($class_dependency)) {
            /** @var \SimpleComplex\Utils\Dependency|\Psr\Container\ContainerInterface $container */
            $container = call_user_func($class_dependency . '::container');
            if ($container->has('logger')) {
                $logger = $container->get('logger');
            }
        }

        $msg = '' . $message . (!$message ? '' : "\n") . $this->__toString();
        // Do replace context vars if no PSR logger.
        if ($context && !$logger) {
            foreach ($context as $k => $v) {
                $msg = str_replace('{' . $k . '}', '' . $v, $msg);
            }
        }

        if ($logger) {
            $logger->log($level, $msg, $context);
        }
        else {
            if (PHP_SAPI != 'cli') {
                $msg = str_replace(["\r", "\n"], ['', ' '], $msg);
            }
            error_log('' . $level . ': ' . $msg);
        }
    }

    /**
     * @deprecated  Use toArray() instead, this method will removed soon.
     *
     * @see Inspector::toArray()
     *
     * Triggers PHP error; E_USER_DEPRECATED.
     *
     * @return array
     */
    public function get(): array
    {
        @trigger_error(
            __CLASS__ . '::' . __METHOD__ . ' method is deprecated and will be removed soon, use toArray() instead.',
            E_USER_DEPRECATED
        );
        return $this->toArray();
    }

    /**
     * Whether inspection output length exceeds maximum.
     *
     * @see Inspector::OUTPUT_DEFAULT
     * @see Inspector::$options['output_max']
     *
     * @return bool
     */
    public function exceedsLength(): bool
    {
        if ($this->length > $this->options['output_max']) {
            $this->abort = true;
            if (!isset($this->warnings['length'])) {
                if ($this->kind == 'variable') {
                    $this->warnings['length'] = 'Variable inspection aborted - output length ' . $this->length
                        . ' exceeds output_max ' . $this->options['output_max'] . '.'
                        . ' Using depth ' . $this->options['depth'] . ' and truncate ' . $this->options['truncate'] . '.'
                        . ' Try less depth or truncate value.';
                }
                else {
                    $this->warnings['length'] = 'Trace inspection aborted - output length ' . $this->length
                        . ' exceeds output_max ' . $this->options['output_max'] . '.'
                        . ' Using limit ' . $this->options['limit'] . ', depth ' . $this->options['depth']
                        . ' and truncate ' . $this->options['truncate'] . '.'
                        . ' Try less limit, depth or truncate value.';
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @var int
     */
    protected static int $requestTime = -1;

    /**
     * Whether inspection duration is about to exceed maximum.
     *
     * @see Inspector::EXEC_TIMEOUT_DEFAULT
     * @see Inspector::$options['exectime_percent']
     *
     * Check if time exceeds request start time plus
     * option 'exectime_percent'/EXEC_TIMEOUT_DEFAULT
     * percent of max_execution_time.
     *
     * @return bool
     */
    public function exceedsTime(): bool
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
            if (!isset($this->warnings['time'])) {
                if ($this->kind == 'variable') {
                    $this->warnings['time'] = 'Variable inspection aborted after ' . $this->options['exectime_percent']
                        . '% of PHP max_execution_time ' . $timeout . ' passed,'
                        . ' using depth ' . $this->options['depth'] . '.'
                        . ' Try less depth.';
                }
                else {
                    $this->warnings['time'] = 'Trace inspection aborted after ' . $this->options['exectime_percent']
                        . '% of PHP max_execution_time ' . $timeout . ' passed,'
                        . ' using limit ' . $this->options['limit'] . ' and depth ' . $this->options['depth'] . '.'
                        . ' Try less limit or depth.';
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @var int
     */
    protected static int $nInspections = 0;

    /**
     * @recursive
     *
     * @param mixed $subject
     * @param int $depth
     *
     * @return string
     *
     * @throws \LogicException
     *      Failing recursion depth control.
     */
    protected function nspct(mixed $subject, int $depth = 0): string
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
        if (($is_object = is_object($subject)) || ($is_array = is_array($subject))) {
            // Throwable.
            if ($is_object && $subject instanceof \Throwable) {
                $file = $subject->getFile();
                $msg = $subject->getMessage();
                if ($this->rootDirLength) {
                    $file = $this->proxy->rootDirReplace($file, true);
                    $msg = $this->proxy->rootDirReplace($msg);
                }
                return '(' . get_class($subject) . ':' . $subject->getCode(). ')@'
                    . $file . ':' . $subject->getLine()
                    . ': ' . addcslashes($msg, "\0..\37");
            }
            // Containers.
            if ($is_array) {
                $output = '(array:';
                $n_elements = count($subject);
                // Numberically indexed array?
                if (!$n_elements || ctype_digit(join('', array_keys($subject)))) {
                    $is_num_array = true;
                }
            }
            // Treat object as a container.
            else {
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
                }
                elseif (method_exists($subject, '__debugInfo')) {
                    // Don't like to assign argument, despite legal and without
                    // side effects in PHP (when not argument by &$reference).
                    // But assigning (array) $subject value to a var would
                    // result in copying; not optimal performancewise.
                    $subject = $subject->__debugInfo();
                    $n_elements = count($subject);
                }
                else {
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
                        if (is_string($element)) {
                            $len_bytes = strlen($element);
                            if (!$len_bytes) {
                                $output .= $key . ': (string:0:0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                            }
                            else {
                                $output .= $key . ': (string:' . $this->proxy->getUnicode()->strlen($element) . ':'
                                    . $len_bytes . ':0) ' . static::FORMAT['quote'] . '...' . static::FORMAT['quote'];
                            }
                        }
                        else {
                            $output .= $key . ': (' . static::getType($element) . ') *';
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
                        . (!$subject ? '0' : static::numberToString($subject));
                }
                break;

            case 'string':
                $output = '(string:';
                // (string:n0:n1|n2): n0 ~ multibyte length, n1 ~ ascii length,
                // n2 only occurs if truncation.
                $len_bytes = strlen($subject);
                if (!$len_bytes) {
                    $output .= '0:0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                }
                elseif (!$this->proxy->getUnicode()->validate($subject)) {
                    // Last delimiter is pipe because logstash may fail
                    // on N:N:N sequence.
                    $output .= '?:' . $len_bytes . '|0) *INVALID_UTF8*';
                }
                else {
                    $len_unicode = $this->proxy->getUnicode()->strlen($subject);
                    $output .= $len_unicode . ':' . $len_bytes;
                    $truncate = $this->options['truncate'];
                    if (!$truncate) {
                        $output .= ':0) ' . static::FORMAT['quote'] . static::FORMAT['quote'];
                    }
                    else {
                        $trunced_to = 0;

                        // Replace site root?
                        if (
                            $this->rootDirLength
                            && $len_unicode >= $this->rootDirLength
                            && (
                                $this->proxy->getUnicode()->strpos($subject, '/') !== false
                                || (DIRECTORY_SEPARATOR == '\\' && $this->proxy->getUnicode()->strpos($subject, '\\') !== false)
                            )
                        ) {
                            $siteroot_replace = true;
                        }
                        else {
                            $siteroot_replace = false;
                        }

                        // Long string; shorten before replacing.
                        if ($len_unicode > $truncate) {
                            // Replace document root before truncation?
                            if ($siteroot_replace && $truncate < $this->rootDirLength) {
                                $siteroot_replace = false;
                                $subject = $this->proxy->rootDirReplace($subject);
                            }
                            $subject = $this->proxy->getUnicode()->substr($subject, 0, $truncate);
                            $trunced_to = $truncate;
                        }
                        // Remove document root after truncation.
                        if ($siteroot_replace) {
                            $subject = $this->proxy->rootDirReplace($subject);
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
                        if ($this->proxy->getUnicode()->strlen($subject) > $truncate) {
                            $subject = $this->proxy->getUnicode()->substr($subject, 0, $truncate);
                            $trunced_to = $truncate;
                        }

                        if ($trunced_to) {
                            // Last delimiter is pipe because logstash may fail
                            // on N:N:N sequence.
                            $output .= '|' . $trunced_to;
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
    protected function trc(?\Throwable $throwableOrNull): string
    {
        // Received Throwable, by arg.
        if ($throwableOrNull) {
            $thrwbl_class = get_class($throwableOrNull);
            if (!$this->code) {
                $this->code = $throwableOrNull->getCode();
            }
            $trace = $throwableOrNull->getTrace();
            // Enforce wrappers and trace limit.
            $n_full_stack = count($trace);
            if ($this->options['wrappers'] && $this->options['wrappers'] < $n_full_stack) {
                array_splice($trace, 0, $this->options['wrappers']);
                $n_full_stack -= $this->options['wrappers'];
            }
            if ($n_full_stack > $this->options['limit']) {
                array_splice($trace, $this->options['limit']);
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
            $self_files = array_keys(static::LIB_FILENAMES);
            $i_frame = -1;
            for ($i = 0; $i < $le; ++$i) {
                if (
                    !empty($trace[$i]['file'])
                    && !in_array(basename($trace[$i]['file']), $self_files)
                ) {
                    $i_frame = $i;
                    break;
                }
            }
            if ($i_frame > -1) {
                $trace = array_slice($trace, $i_frame);
            }
            // Enforce wrappers and trace limit.
            $n_full_stack = count($trace);
            if ($this->options['wrappers'] && $this->options['wrappers'] < $n_full_stack) {
                array_splice($trace, 0, $this->options['wrappers']);
                $n_full_stack -= $this->options['wrappers'];
            }
            if ($n_full_stack > $this->options['limit']) {
                // Plus one because we need the bucket holding the initial event.
                array_splice($trace, $this->options['limit'] + 1);
            }
        }

        $delim = static::FORMAT['delimiter'];

        // If exception: resolve its origin and render code and message.
        if ($thrwbl_class) {
            $file = $throwableOrNull->getFile();
            $msg = $throwableOrNull->getMessage();
            if ($this->rootDirLength) {
                $file = $this->proxy->rootDirReplace($file, true);
                $msg = $this->proxy->rootDirReplace($msg);
            }
            $output = $thrwbl_class . '(' . $throwableOrNull->getCode() . ')'
                . '@' . $file . ':' . $throwableOrNull->getLine()
                . $delim
                . addcslashes($msg, "\0..\37");
            if (($previous = $throwableOrNull->getPrevious())) {
                $file = $previous->getFile();
                $msg = $previous->getMessage();
                if ($this->rootDirLength) {
                    $file = $this->proxy->rootDirReplace($file, true);
                    $msg = $this->proxy->rootDirReplace($msg);
                }
                $output .= $delim . 'Previous: '
                    . get_class($previous) . '(' . $previous->getCode() . ')@'
                    . $file . ':' . $previous->getLine() . $delim
                    . addcslashes($msg, "\0..\37");
            }
            unset($previous);
        } else {
            $output = 'Backtrace';
        }

        // Iterate stack frames.
        $i_frame = -1;
        foreach ($trace as $frame) {
            $output .= $delim . (++$i_frame) . ' ' . static::FORMAT['trace_spacer'];
            if (isset($frame['file'])) {
                $file = $frame['file'];
                if ($this->rootDirLength) {
                    $file = $this->proxy->rootDirReplace($file, true);
                }
                $output .= $delim . '@' . $file . ':' . ($frame['line'] ?? '?');
            }
            else {
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
                    $output .= $delim . 'method: ' . $class . ($type ? $type : '::') . $function;
                }
            }

            // Args.
            if (isset($frame['args'])) {
                $le = count($frame['args']);
                $output .= ' (args:' . $le . ')';
                if ($le && $this->options['depth']) {
                    for ($i = 0; $i < $le; ++$i) {
                        $output .= $delim . static::FORMAT['indent'] . $this->nspct($frame['args'][$i], 1);
                    }
                }
            }
        }
        $output .= $delim
            . '+' . ($n_full_stack <= $this->options['limit'] ? 0 : $n_full_stack - $this->options['limit'])
            . ' ' . static::FORMAT['trace_end'];

        return $output;
    }

    /**
     * Get file and line of outmost call to an inspect function or public method.
     *
     * Replaces document root with [document_root].
     *
     * @return string
     *      Empty if no success.
     */
    protected function fileLine(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // Find first frame whose file isn't named like our library files.
        $le = count($trace);
        $i_frame = -1;
        $self_files = array_keys(static::LIB_FILENAMES);
        for ($i = 1; $i < $le; ++$i) {
            if (
                !empty($trace[$i]['file'])
                && !in_array(basename($trace[$i]['file']), $self_files)
            ) {
                $i_frame = $i;
                break;
            }
        }
        if (
            $i_frame > -1
            && (!$this->options['wrappers'] || !empty($trace[$i_frame += $this->options['wrappers']]['file']))
        ) {
            $file = $trace[$i_frame]['file'];
            if ($this->rootDirLength) {
                $file = $this->proxy->rootDirReplace($file, true);
            }
            return $file . ':' . ($trace[$i_frame]['line'] ?? '?');
        }
        return '';
    }

    /**
     * @return string
     */
    protected function preface(): string
    {
        if ($this->kind == 'variable') {
            $preface = '[Inspect variable|#' . static::$nInspections . '|depth:' . $this->options['depth']
                . '|truncate:' . $this->options['truncate'] . ']';
        }
        else {
            $preface = '[Inspect trace|#' . static::$nInspections . '|limit:' . $this->options['limit']
                . '|depth:' . $this->options['depth'] . '|truncate:' . $this->options['truncate'] . ']';
        }
        $preface .= '@' . $this->fileLine;
        if ($this->code) {
            // Redundant if exception trace, but we don't know here if it's
            // exception or backtrace.
            $preface .= static::FORMAT['newline'] . 'Code: ' . $this->code;
        }
        if ($this->warnings) {
            $preface .= static::FORMAT['newline'] . join(static::FORMAT['newline'], $this->warnings);
        }
        return $preface;
    }

    /**
     * Get subject class name or (non-object) type.
     *
     * Counter to native gettype() this method returns:
     * - class name instead of 'object'
     * - 'float' instead of 'double'
     * - 'null' instead of 'NULL'
     *
     * Like native gettype() this method returns:
     * - 'boolean' not 'bool'
     * - 'integer' not 'int'
     * - 'unknown type' for unknown type
     *
     * @param mixed $subject
     *
     * @return string
     */
    public static function getType(mixed $subject): string
    {
        if (!is_object($subject)) {
            $type = gettype($subject);
            return match ($type) {
                'double' => 'float',
                'NULL' => 'null',
                default => $type,
            };
        }
        return get_class($subject);
    }

    /**
     * Convert number to string avoiding E-notation for numbers outside system
     * precision range.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      If arg var isn't integer/float nor number-like when stringified.
     */
    public static function numberToString(mixed $var): string
    {
        static $precision;
        if (!$precision) {
            $precision = pow(10, (int)ini_get('precision'));
        }
        $v = '' . $var;
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException('Arg var is not integer/float nor number-like when stringified.');
        }

        // If within system precision, just string it.
        return ($v > -$precision && $v < $precision) ? $v : number_format((float) $v, 0, '.', '');
    }
}
