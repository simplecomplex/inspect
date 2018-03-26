<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Utils\Interfaces\SectionedMapInterface;
use SimpleComplex\Utils\SectionedMap;
use SimpleComplex\Utils\Unicode;
use SimpleComplex\Utils\Sanitize;
use SimpleComplex\Validate\Validate;

/**
 * Variable analyzer and exception tracer.
 *
 * Mostly proxy class for Inspector.
 *
 * @dependency-injection-container inspect
 *      Suggested ID of the JsonLog instance.
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
     * @deprecated Use a dependency injection container instead.
     * @see \SimpleComplex\Utils\Dependency
     * @see \Slim\Container
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
     *  - (bool) escape_html:       false (Inspector::ESCAPE_HTML)
     *  - (int) output_max:         ~1Mb (Inspector::OUTPUT_DEFAULT)
     *  - (int) exectime_percent:   90 (Inspector::EXEC_TIMEOUT_DEFAULT)
     *
     * See also ../config-ini/inspect.ini
     *
     * @var SectionedMapInterface
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
     * @see \SimpleComplex\Utils\Interfaces\SectionedMapInterface
     * @see \SimpleComplex\Config\Interfaces\SectionedConfigInterface
     * @see \SimpleComplex\Config\EnvSectionedConfig
     *
     * @code
     * Dependency::genericSet('inspect', function() use ($container) {
     *     return new \SimpleComplex\Inspect\Inspect($container->get('config'));
     * });
     * Dependency::genericSet('inspect', function() {
     *     return new \SimpleComplex\Inspect\Inspect([
     *         'trace_limit' => 3,
     *     ]);
     * });
     * Dependency::genericSet('inspect', function() {
     *     // Use \SimpleComplex\Config\EnvSectionedConfig, if exists.
     *     return new \SimpleComplex\Inspect\Inspect();
     * });
     * @endcode
     *
     * @param SectionedMapInterface|object|array|null $config
     *      Non-SectionedMapInterface object|array: will be used
     *          as JsonLog specific settings.
     *      Null: instance will on demand use
     *          \SimpleComplex\Config\EnvSectionedConfig, if exists.
     */
    public function __construct($config = null)
    {
        // Dependencies.--------------------------------------------------------
        // Extending class' constructor might provide instances by other means.
        if (!$this->config && isset($config)) {
            if ($config instanceof SectionedMapInterface) {
                $this->config = $config;
            } else {
                $this->config = (new SectionedMap())->setSection(static::CONFIG_SECTION, $config);
            }
        }

        // Business.------------------------------------------------------------
        // None.
    }

    /**
     * @deprecated
     *      This method will be removed; doesn't solve anything in terms
     *      of mutual dependency, and there hardly is any such issue anyway.
     *
     * @param SectionedMapInterface $config
     *
     * @return void
     */
    public function setConfig(SectionedMapInterface $config) /*: void*/
    {
        $this->config = $config;
    }

    /**
     * Load dependencies on demand.
     *
     * @see \SimpleComplex\Config\EnvSectionedConfig
     *
     * @return void
     */
    protected function loadDependencies() /*: void*/
    {
        if (!$this->validate) {
            $this->validate = Validate::getInstance();

            if (!$this->config) {
                // Use enviroment variable wrapper config class if exists;
                // fall back on empty sectioned map.
                if (class_exists('\\SimpleComplex\\Config\\EnvSectionedConfig')) {
                    $this->config = call_user_func('\\SimpleComplex\\Config\\EnvSectionedConfig::getInstance');
                } else {
                    $this->config = new SectionedMap();
                }
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
