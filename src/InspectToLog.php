<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2018-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

/**
 * @deprecated  Abandoned and defunct.
 */
class InspectToLog
{
    /**
     * @deprecated  Abandoned and defunct.
     *
     * @param string $logLevel
     * @param string $message
     * @param mixed $subject
     * @param array $context
     *
     * @throws \LogicException
     *      This class and constructor are defunct; abandoned.
     */
    public function __construct(string $logLevel, string $message, $subject, array $context = [])
    {
        throw new \LogicException(
            'InspectToLog is abandoned and defunct'
            . ', due to no globally supported means of getting and using dependency injection containers.'
        );
    }
}
