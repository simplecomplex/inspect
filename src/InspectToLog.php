<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Utils\Dependency;

/**
 * Log analysis of variable or exception with a single (constructor) call.
 *
 * Convenience class.
 */
class InspectToLog
{
    /**
     * Log analysis of variable or exception.
     *
     * @code
     * new \SimpleComplex\Inspect\InspectToLog('debug', 'message', 'subject');
     * @endcode
     *
     * @param string $logLevel
     *      debug|info|notice|warning|error|alert|critical|emergency.
     * @param string $message
     * @param mixed $subject
     *      The variable or exception to analyse.
     * @param array $context
     *      Logging context.
     *
     * @throws \LogicException
     *      Dependency injection container misses either 'logger' or 'inspect'.
     */
    public function __construct(string $logLevel, string $message, $subject, array $context = [])
    {
        /** @var \Psr\Container\ContainerInterface $container */
        $container = Dependency::container();
        if (!$container->has('logger')) {
            throw new \LogicException('Dependency container has no \'logger\'.');
        }
        if (!$container->has('inspect')) {
            throw new \LogicException('Dependency container has no \'inspect\'.');
        }
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $container->get('logger');
        /** @var \SimpleComplex\Inspect\Inspect $inspect */
        $inspect = $container->get('inspect');
        $logger->log(
            $logLevel,
            ($message === '' ? '' : ($message . "\n"))
                . $inspect->inspect($subject, ['wrappers' => 1]),
            $context
        );
    }
}
