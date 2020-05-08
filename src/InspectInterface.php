<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;


interface InspectInterface
{
    /**
     * Inspect variable or trace exception.
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return InspectorInterface
     */
    public function inspect($subject, $options = []) : InspectorInterface;

    /**
     * Inspect variable.
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return InspectorInterface
     */
    public function variable($subject, $options = []) : InspectorInterface;

    /**
     * Trace exception or do back-trace.
     *
     * @param \Throwable|null $throwableOrNull
     * @param array $options
     *
     * @return InspectorInterface
     */
    public function trace(?\Throwable $throwableOrNull, $options = []) : InspectorInterface;
}
