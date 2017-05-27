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

/**
 * Mostly proxy class for Inspector.
 *
 * @package SimpleComplex\Inspect
 */
class Inspect
{
    /**
     * @see GetInstanceTrait
     *
     * List of previously instantiated objects, by name.
     * @protected
     * @static
     * @var array $instances
     *
     * Reference to last instantiated instance.
     * @protected
     * @static
     * @var static $lastInstance
     *
     * Get previously instantiated object or create new.
     * @public
     * @static
     * @see GetInstanceTrait::getInstance()
     *
     * Kill class reference(s) to instance(s).
     * @public
     * @static
     * @see GetInstanceTrait::flushInstance()
     */
    use GetInstanceTrait;

    /**
     * Class name of \SimpleComplex\JsonLog\JsonLogEvent or extending class.
     *
     * @code
     * // Overriding class must use fully qualified (namespaced) class name.
     * const CLASS_JSON_LOG_EVENT = \Package\Library\CustomJsonLogEvent::class;
     * @endcode
     *
     * @see \SimpleComplex\JsonLog\JsonLogEvent
     *
     * @var string
     */
    const CLASS_INSPECTOR = Inspector::class;

    /**
     * Class name of \SimpleComplex\Filter\Unicode or extending class.
     *
     * @var string
     */
    const CLASS_UNICODE = Unicode::class;

    /**
     * Class name of \SimpleComplex\Filter\Sanitize or extending class.
     *
     * @var string
     */
    const CLASS_SANITIZE = Sanitize::class;

    /**
     * Class name of \SimpleComplex\Filter\Sanitize or extending class.
     *
     * @var string
     */
    const CLASS_VALIDATE = Validate::class;

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
     * Proxy class for Inspector.
     *
     * Checks and resolves all dependencies, whereas Inspector use them unchecked.
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
     * @param CacheInterface|null $config
     *      PSR-16 based configuration instance, if any.
     */
    public function __construct($config = null)
    {
        $this->config = $config;

        $this->unicode = static::CLASS_UNICODE == Unicode::class ? Unicode::getInstance() :
            forward_static_call(static::CLASS_UNICODE . '::getInstance');
        $this->sanitize = static::CLASS_SANITIZE == Sanitize::class ? Sanitize::getInstance() :
            forward_static_call(static::CLASS_SANITIZE . '::getInstance');
        $this->validate = static::CLASS_VALIDATE == Validate::class ? Validate::getInstance() :
            forward_static_call(static::CLASS_VALIDATE . '::getInstance');
    }

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function inspect($subject, array $options = [])
    {
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            [
                'config' => $this->config,
                'unicode' => $this->unicode,
                'sanitize' => $this->sanitize,
                'validate' => $this->validate,
            ],
            $subject,
            $options
        );
    }

    /**
     * Force variable inspection, even if subject is a throwable.
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function variable($subject, array $options = [])
    {
        $options['kind'] = 'variable';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            [
                'config' => $this->config,
                'unicode' => $this->unicode,
                'sanitize' => $this->sanitize,
                'validate' => $this->validate,
            ],
            $subject,
            $options
        );
    }

    /**
     * Force back-tracing, if arg $throwableOrNull isn't a Throwable.
     *
     * @param \Throwable|null $throwableOrNull
     * @param array $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function trace($throwableOrNull, array $options = [])
    {
        $options['kind'] = 'trace';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            [
                'config' => $this->config,
                'unicode' => $this->unicode,
                'sanitize' => $this->sanitize,
                'validate' => $this->validate,
            ],
            $throwableOrNull,
            $options
        );
    }

    /**
     * Overcome mutual dependency, provide a config object after instantiation.
     *
     * This class does not need a config object at all, if defaults are adequate.
     *
     * @param CacheInterface $config
     *
     * @return void
     */
    public function setConfig(CacheInterface $config) : void
    {
        $this->config = $config;
    }
}
