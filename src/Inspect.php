<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait;
use SimpleComplex\Utils\Unicode;
use SimpleComplex\Utils\Sanitize;
use SimpleComplex\Validate\Validate;

/**
 * Mostly proxy class for Inspector.
 *
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @package SimpleComplex\Inspect
 */
class Inspect
{
    /**
     * @see \SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait
     *
     * First object instantiated via this method, disregarding class called on.
     * @public
     * @static
     * @see \SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait::getInstance()
     */
    use GetInstanceOfFamilyTrait;

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
     * Class name of \SimpleComplex\Utils\Sanitize or extending class.
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
    public function __construct(/*?CacheInterface*/ $config = null)
    {
        $this->config = $config;

        $this->unicode = Unicode::getInstance();
        $this->sanitize = Sanitize::getInstance();
        $this->validate = Validate::getInstance();
    }

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @param mixed $subject
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function inspect($subject, $options = []) : Inspector
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
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function variable($subject, $options = []) : Inspector
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
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     */
    public function trace(/*?\Throwable*/ $throwableOrNull, $options = []) : Inspector
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
    public function setConfig(CacheInterface $config) /*: void*/
    {
        $this->config = $config;
    }
}
