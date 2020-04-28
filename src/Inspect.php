<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Inspect\Helper\Config;
use SimpleComplex\Inspect\Helper\Unicode;

/**
 * Variable analyzer and exception tracer.
 *
 * Proxy class for Inspector.
 *
 * @code
 * try {
 *     $subject = unknown_variable();
 *     if (!$subject || !$subject instanceof ExpectedClass::class) {
 *         // Do stringify Inspector instance when logging.
 *         $logger->warning('Unexpected unknown_variable():' . "\n" . $inspect->inspect($subject));
 *     }
 * }
 * catch (\Throwable $xcptn) {
 *     $logger->warning('Unexpected unknown_variable():' . "\n" . $inspect->trace($xcptn));
 * }
 * @endcode
 *
 * @dependency-injection-container-id inspect
 *      Suggested ID of the JsonLog instance.
 *
 * @see Inspector
 *
 * @package SimpleComplex\Inspect
 */
class Inspect implements InspectInterface
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
     *
     * @return Inspect
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = (new static())->configure();
        }
        return static::$instance;
    }

    /**
     * Class name of \SimpleComplex\Inspect\Helper\Config or extending class.
     *
     * @see \SimpleComplex\Inspect\Helper\Config
     *
     * @var string
     */
    const CLASS_CONFIG = Config::class;

    /**
     * Class name of \SimpleComplex\Inspect\Helper\Unicode or extending class.
     *
     * @see \SimpleComplex\Inspect\Helper\Unicode
     *
     * @var string
     */
    const CLASS_UNICODE = Unicode::class;

    /**
     * Class name of \SimpleComplex\Inspect\Inspector or extending class.
     *
     * @see \SimpleComplex\Inspect\Inspector
     *
     * @var string
     */
    const CLASS_INSPECTOR = Inspector::class;

    /**
     * @var \SimpleComplex\Inspect\Helper\Config
     */
    public $config;

    /**
     * @var \SimpleComplex\Inspect\Helper\Unicode
     */
    public $unicode;

    /**
     * @var bool
     */
    protected $configured = false;

    /*
     * No explicit constructor, to allow use of framework specific parameters.
     *
    public function __construct()
    {
    }*/

    /**
     * This method must be called before use of the class.
     *
     * Otherwise similar work must be done in constructor or other method.
     *
     * @param object|null $config
     *      Null if no custom value(s), overriding Inspecter defaults.
     *
     * @return Inspect|self
     */
    public function configure(?object $config = null) : self
    {
        if (!$this->configured) {
            $class_config = static::CLASS_CONFIG;
            // Pass arg $config to new Config instance,
            // unless $config already is such.
            $this->config = $config && is_a($config, $class_config) ? $config : new $class_config($config);

            $class_unicode = static::CLASS_UNICODE;
            $this->unicode = new $class_unicode();

            $this->configured = true;
        }

        return $this;
    }

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function inspect($subject, $options = []) : InspectorInterface
    {
        if (!$this->configured) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
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
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function variable($subject, $options = []) : InspectorInterface
    {
        if (!$this->configured) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
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
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function trace(/*?\Throwable*/ $throwableOrNull, $options = []) : InspectorInterface
    {
        if (!$this->configured) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
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
