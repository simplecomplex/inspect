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
 * To accommodate Inspect in framework, extend this class.
 * @see Config::getViaInnerConfigInstance()
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
     * @var string
     */
    protected $classAlias = 'unknown';

    /**
     * @var object|null
     */
    protected $config;

    /**
     * @param object|null $config
     *      Null: If no custom value(s), overriding Inspecter defaults.
     */
    public function __construct(?object $config)
    {
        if ($config) {
            if (class_exists($class = '\\Drupal\\Core\\Config\\ImmutableConfig')
                && is_a($config, $class)
            ) {
                $this->classAlias = 'drupal_ImmutableConfig';
            }
            elseif (class_exists($interface = '\\SimpleComplex\\Config\\Interfaces\\SectionedConfigInterface')
                && is_subclass_of($config, $interface)
            ) {
                $this->classAlias = 'simplecomplex_SectionedConfigInterface';
            }
            else {
                $this->classAlias = 'unknown';
            }

            $this->config = $config;
        }
    }

    /**
     * Get a configuration property.
     *
     * @see Config::getViaInnerConfigInstance()
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
        if (array_key_exists($name, static::PROPERTIES)) {
            if (!$this->config) {
                return null;
            }
            return $this->getViaInnerConfigInstance($name);
        }
        throw new \OutOfBoundsException(
            'Inspect configuration ' . get_class($this) . ' instance exposes no property[' . $name . '].'
        );
    }

    /**
     * Override this method to accommodate to framework.
     *
     * Knows how to work with:
     * - \Drupal\Core\Config\ImmutableConfig
     * - \SimpleComplex\Config\Interfaces\SectionedConfigInterface
     *
     * Fallback: $this->config->{$key}
     *
     * Method name deliberately ugly; to mitigate collision.
     *
     * @param string $key
     *
     * @return mixed|null
     *      Null on non-existent key.
     */
    protected function getViaInnerConfigInstance(string $key)
    {
        switch ($this->classAlias) {
            case 'drupal_ImmutableConfig':
                /**
                 * \Drupal\Core\Config\ImmutableConfig
                 */
                return $this->config->get($key) ?? null;
            case 'simplecomplex_SectionedConfigInterface':
                /**
                 * \SimpleComplex\Config\Interfaces\SectionedConfigInterface
                 */
                return $this->config->get('lib_simplecomplex_inspect', $key);
        }

        return $this->config->{$key} ?? null;
    }
}
