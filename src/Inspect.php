<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Config\SectionedConfigInterface;
use SimpleComplex\Config\EnvSectionedConfig;
use SimpleComplex\Utils\Unicode;
use SimpleComplex\Utils\Sanitize;
use SimpleComplex\Validate\Validate;

/**
 * Variable analyzer and exception tracer.
 *
 * Mostly proxy class for Inspector.
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @see Inspector
 *
 * @package SimpleComplex\Inspect
 */
class Inspect
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Inspect
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return Inspect
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * Class name of \SimpleComplex\JsonLog\JsonLogEvent or extending class.
     *
     * @code
     * // Overriding class must use fully qualified (namespaced) class name.
     * const CLASS_INSPECTOR = \Package\Library\CustomInspector::class;
     * @endcode
     *
     * @see \SimpleComplex\JsonLog\JsonLogEvent
     *
     * @var string
     */
    const CLASS_INSPECTOR = Inspector::class;

    /**
     * Conf var default namespace.
     *
     * @var string
     */
    const CONFIG_SECTION = 'lib_simplecomplex_inspect';

    /**
     *  Config vars, and their effective defaults:
     *  - (int) trace_limit:        5 (Inspector::TRACE_LIMIT_DEFAULT)
     *  - (int) truncate:           1000 (Inspector::TRUNCATE_DEFAULT)
     *  - (int) output_max:         ~1Mb (Inspector::OUTPUT_DEFAULT)
     *  - (int) exectime_percent:   90 (Inspector::EXEC_TIMEOUT_DEFAULT)
     *
     * @var SectionedConfigInterface|null
     */
    public $config;

    /**
     * @var Unicode
     */
    public $unicode;

    /**
     * @var Sanitize
     */
    public $sanitize;

    /**
     * @var Validate
     */
    public $validate;

    /**
     * Proxy class for Inspector.
     *
     * @code
     * use \SimpleComplex\JsonLog\JsonLog;
     * use \SimpleComplex\Inspect\Inspect;
     *
     * $logger = JsonLog::getInstance();
     * $inspect = Inspect::getInstance();
     *
     * $subject = unknown_variable();
     * if (!$subject || !$subject instanceof ExpectedClass::class) {
     *
     *     // Correct: stringify, implicitly using Inspector's __toString() method.
     *     $logger->warning('Unexpected unknown_variable(): ' . $inspect->inspect($subject));
     *
     *     // Risky: logger may not accept an (Inspector) object as arg message (though JsonLog do).
     *     $logger->warning($inspect->inspect($subject));
     * }
     * @endcode
     *
     * @see JsonLog::setConfig()
     * @see \SimpleComplex\Config\EnvSectionedConfig
     *
     * @param SectionedConfigInterface|null $config
     *      Uses/instantiates SimpleComplex\Config\EnvSectionedConfig
     *      _on demand_, as fallback.
     */
    public function __construct(/*?SectionedConfigInterface*/ $config = null)
    {
        // Dependencies.--------------------------------------------------------
        // Extending class' constructor might provide instances by other means.
        if (!$this->config && isset($config)) {
            $this->setConfig($config);
        }

        // Business.------------------------------------------------------------
        // None.
    }

    /**
     * Overcome mutual dependency, provide a config object after instantiation.
     *
     * This class does not need a config object at all, if defaults are adequate.
     *
     * @param SectionedConfigInterface $config
     *
     * @return void
     */
    public function setConfig(SectionedConfigInterface $config) /*: void*/
    {
        $this->config = $config;
    }

    /**
     * Load dependencies on demand.
     *
     * @return void
     */
    protected function loadDependencies() /*: void*/
    {
        if (!$this->validate) {
            $this->validate = Validate::getInstance();

            if (!$this->config) {
                $this->setConfig(EnvSectionedConfig::getInstance());
            }
            if (!$this->unicode) {
                $this->unicode = Unicode::getInstance();
            }
            if (!$this->sanitize) {
                $this->sanitize = Sanitize::getInstance();
            }
        }
    }

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @code
     * # CLI
     * \SimpleComplex\Inspect\Inspect::getInstance()->inspect($GLOBALS);
     * @endcode
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function inspect($subject, $options = []) : Inspector
    {
        // Init.----------------------------------------------------------------
        $this->loadDependencies();

        // Business.------------------------------------------------------------

        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $subject,
            $options
        );
    }

    /**
     * Force variable inspection, even if subject is a throwable.
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function variable($subject, $options = []) : Inspector
    {
        // Init.----------------------------------------------------------------
        $this->loadDependencies();

        // Business.------------------------------------------------------------

        $options['kind'] = 'variable';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $subject,
            $options
        );
    }

    /**
     * Force back-tracing, if arg $throwableOrNull is null (back-trace).
     *
     * @see Inspector::$options
     *
     * @param \Throwable|null $throwableOrNull
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function trace(/*?\Throwable*/ $throwableOrNull, $options = []) : Inspector
    {
        // Init.----------------------------------------------------------------
        $this->loadDependencies();

        // Business.------------------------------------------------------------

        $options['kind'] = 'trace';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $throwableOrNull,
            $options
        );
    }
}
