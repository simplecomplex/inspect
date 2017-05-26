<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Inspect;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Filter\Unicode;
use SimpleComplex\Filter\Sanitize;

/**
 * Mostly proxy class for Inspector.
 *
 * @package SimpleComplex\Inspect
 */
class Inspect
{
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
     * Checks and resolves all dependencies, whereas Inspector use them unchecked.
     *
     * @param array $softDependencies {
     *      @var CacheInterface|null $config
     *      @var Unicode|null $unicode Effective default: SimpleComplex\Filter\Unicode.
     *      @var Sanitize|null $sanitize Effective default: SimpleComplex\Filter\Sanitize.
     * }
     */
    public function __construct(
        array $softDependencies = ['config' => null, 'unicode' => null, 'sanitize' => null]
    ) {
        $config = $softDependencies['config'] ?? null;
        if ($config && is_object($config) && $config instanceof CacheInterface) {
            $this->config = $config;
        }

        $unicode = $softDependencies['unicode'] ?? null;
        $this->unicode = $unicode && is_object($unicode) && $unicode instanceof Unicode ?
            $unicode : Unicode::getInstance();

        $sanitize = $softDependencies['unicode'] ?? null;
        $this->sanitize = $sanitize && is_object($sanitize) && $sanitize instanceof Sanitize ?
            $sanitize : Sanitize::getInstance();
    }
}
