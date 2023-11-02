<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2020-2023 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

/**
 * Produces variable inspection or backtrace,
 * stringable and loggable.
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
     * @param array|int $options
     */
    public function __construct(InspectInterface $proxy, mixed $subject, array|int $options = []);

    /**
     * @param bool $noPreface
     *
     * @return string
     */
    public function toString(bool $noPreface = false): string;

    /**
     * @return string
     */
    public function __toString(): string;

    /*
     * List of inspection properties.
     *
     * Available for alternative ways of using the products of an inspection.
     *
     * @return array
     *
    public function toArray(): array;
     */

    /**
     * Convenience method allowing method chaining,
     * e.g. inspect->variable(...)->log().
     *
     * Works like PSR log() and may use PSR logger if available.
     *
     * If $message is non-empty it gets prepended to inspection output.
     *
     * @see \Psr\Log\LoggerInterface::log()
     *
     * @param string|int $level
     *      Beware that a strict PSR logger won't accept integer.
     * @param mixed $message
     *      Non-string becomes stringified.
     *      Non-empty gets prepended to inspection output.
     * @param mixed[] $context
     *
     * @return void
     */
    public function log(string|int $level = 'debug', mixed $message = '', array $context = []): void;
}
