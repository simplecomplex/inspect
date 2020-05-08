<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

/**
 * Produces variable inspection or backtrace,
 * accessible via __toString() or Psr\Log-like methods.
 *
 * Does not extend Psr\Log\LoggerInterface in order to prevent dependency.
 *
 * @package SimpleComplex\Inspect
 */
interface InspectorInterface
{
    /**
     * Produces variable inspection or backtrace.
     *
     * @internal  Allowed for InspectInterface only.
     *
     * @param InspectInterface $proxy
     * @param mixed $subject
     * @param array|int|string $options
     */
    public function __construct(InspectInterface $proxy, $subject, $options = []);

    /**
     * @param bool $noPreface
     *
     * @return string
     */
    public function toString($noPreface = false) : string;

    /**
     * @return string
     */
    public function __toString() : string;

    /**
     * Does not extend Psr\Log\LoggerInterface in order to prevent dependency.
     *
     * @see \Psr\Log\LoggerInterface::log()
     *
     * @param string|int $level
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::emergency()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function emergency($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::alert()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function alert($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::critical()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function critical($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::error()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function error($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::warning()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function warning($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::notice()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function notice($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::info()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function info($message, array $context = []);

    /**
     * @see \Psr\Log\LoggerInterface::debug()
     *
     * @param mixed $message
     *      Non-string becomes stringified.
     * @param mixed[] $context
     *
     * @return void
     */
    public function debug($message, array $context = []);

    /**
     * List of inspection properties.
     *
     * Available for alternative ways of using the products of an instance.
     *
     * @return array
     */
    public function get() : array;
}
