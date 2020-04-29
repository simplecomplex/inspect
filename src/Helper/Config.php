<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect\Helper;

use SimpleComplex\Inspect\Inspector;

/**
 * Configuration proxy.
 *
 * We cannot know the workings of a framework's configuration mechanism.
 * To accommodate Inspect in a framework, extend this class.
 * @see Config::__get()
 *
 *
 * @see Inspector::DEPTH_DEFAULT
 * @see Inspector::TRACE_DEPTH_DEFAULT
 * @property-read int|null $depth
 *
 * @see Inspector::TRACE_LIMIT_DEFAULT
 * @property-read int|null $trace_limit
 *
 * @see Inspector::TRUNCATE_DEFAULT
 * @property-read int|null $truncate
 *
 * @see Inspector::ESCAPE_HTML
 * @property-read bool|null $escape_html
 *
 * @see Inspector::OUTPUT_DEFAULT
 * @property-read int|null $output_max
 *
 * @see Inspector::EXEC_TIMEOUT_DEFAULT
 * @property-read int|null $exectime_percent
 *
 *
 * @package SimpleComplex\Inspect
 */
class Config
{
    /**
     * Keys list supported property names.
     *
     * Associative array to make extension simpler;
     * PROPERTIES[...] + Parent::PROPERTIES.
     *
     * @var bool[]
     */
    const PROPERTIES = [
        'depth' => true,
        'trace_limit' => true,
        'truncate' => true,
        'escape_html' => true,
        'output_max' => true,
        'exectime_percent' => true,
    ];

    /**
     * @var object|null
     */
    protected $config;

    /**
     * @param object|null $config
     *      Null if no custom value(s), overriding Inspecter defaults.
     */
    public function __construct(?object $config = null)
    {
        $this->config = $config;
    }

    /**
     * Get a configuration property.
     *
     * Supports simplecomplex configuration regime
     * plus simple direct access: $this->config->{$name} ?? null;
     *
     * @param string $name
     *
     * @return mixed|null
     *
     * @throws \OutOfBoundsException
     *      If unsupported configuration property name.
     */
    public function __get(string $name)
    {
        static $useGetMethod;
        if (array_key_exists($name, static::PROPERTIES)) {
            if (!$this->config) {
                return null;
            }
            if (!$useGetMethod) {
                if (class_exists($interface = '\\SimpleComplex\\Config\\Interfaces\\SectionedConfigInterface')
                    && is_subclass_of($this->config, $interface)
                ) {
                    $useGetMethod = 1;
                }
                else {
                    $useGetMethod = -1;
                }
            }
            if ($useGetMethod == 1) {
                /**
                 * \SimpleComplex\Config\Interfaces\SectionedConfigInterface
                 */
                return $this->config->get('lib_simplecomplex_inspect', $name, null);
            }
            return $this->config->{$name} ?? null;
        }
        throw new \OutOfBoundsException(
            'Inspect configuration ' . get_class($this) . ' instance exposes no property[' . $name . '].'
        );
    }

    /*
     * Drupal override example.
     *
     * Get a configuration property.
     *
     * @param string $name
     *
     * @return mixed|null
     *
     * @throws \OutOfBoundsException
     *      If unsupported configuration property name.
     *
    public function __get(string $name)
    {
        if (array_key_exists($name, static::PROPERTIES)) {
            //  \Drupal\Core\Config\ImmutableConfig
            return $this->config->get($key) ?? null;
        }
        throw new \OutOfBoundsException(
            'Inspect configuration ' . get_class($this) . ' instance exposes no property[' . $name . '].'
        );
    }
    */
}
