<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2023 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Inspect\Helper\Config;
use SimpleComplex\Inspect\Helper\UnicodeInterface;
use SimpleComplex\Inspect\Helper\Unicode;

/**
 * Variable analyzer and exception tracer.
 *
 * Proxy class for Inspector.
 *
 * @code
 * try {
 *     $subject = unknown_variable();
 *     if (!$subject || !$subject instanceof ExpectedClass::class) {
 *         // Do stringify Inspector instance when logging.
 *         $logger->warning('Unexpected unknown_variable():' . "\n" . $inspect->inspect($subject));
 *         // Alternative ease-of-comfort procedure.
 *         $inspect->inspect($subject))->log('warning', 'Unexpected unknown_variable():');
 *     }
 * }
 * catch (\Throwable $xcptn) {
 *     $logger->warning('Unexpected unknown_variable():' . "\n" . $inspect->trace($xcptn));
 * }
 * @endcode
 *
 * @dependency-injection-container-id inspect
 *      Suggested ID of the Inspect instance.
 *
 * @see Inspector
 *
 * @package SimpleComplex\Inspect
 */
class Inspect implements InspectInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Inspect|null
     */
    protected static ?Inspect $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * Don't use method if there's a dependency injection container available.
     *
     * @return Inspect
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(): static
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Class name of \SimpleComplex\Inspect\Helper\Config or extending class.
     *
     * @see \SimpleComplex\Inspect\Helper\Config
     *
     * @var string
     */
    const CLASS_CONFIG = Config::class;

    /**
     * Class name of \SimpleComplex\Inspect\Helper\Unicode or extending class.
     *
     * @see \SimpleComplex\Inspect\Helper\Unicode
     *
     * @var string
     */
    const CLASS_UNICODE = Unicode::class;

    /**
     * Class name of \SimpleComplex\Inspect\Inspector or extending class.
     *
     * @see \SimpleComplex\Inspect\Inspector
     *
     * @var string
     */
    const CLASS_INSPECTOR = Inspector::class;

    /**
     * @see Inspect::rootDirReplace()
     */
    const ROOT_DIR_SUBSTITUTE = '[root dir]';

    /**
     * @var \SimpleComplex\Inspect\Helper\Config|null
     */
    protected ?Config $config = null;

    /**
     * @var \SimpleComplex\Inspect\Helper\UnicodeInterface
     */
    protected UnicodeInterface $unicode;

    /**
     * @var string|null
     */
    protected ?string $rootDir;

    /**
     * Values:
     * - zero: root dir definition not attempted yet
     * - negative: root dir cannot be established
     *
     * @var int
     */
    protected int $rootDirLength = 0;

    /**
     * No parameters, to allow overriding constructor
     * to use framework specific parameters.
     */
    public function __construct()
    {
        $class_unicode = static::CLASS_UNICODE;
        $this->unicode = new $class_unicode();
    }

    /*
     * Drupal override example.
     *
     * @param ConfigFactoryInterface $config_factory
     * @param LoggerChannelFactoryInterface $logger_factory
     *
    public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory)
    {
        parent::__construct();

        $this->getConfig(
            $config_factory->get('inspect.settings')
        );
        $this->loggerFactory = $logger_factory;
    }
    */

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int $options
     *   Integer: maximum depth.
     *   Ignored if not array|int.
     *
     * @return Inspector
     *      Stringable. Chainable.
     */
    public function inspect(mixed $subject, array|int $options = []): InspectorInterface
    {
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $subject,
            $options
        );
    }

    /**
     * Force variable inspection, even if subject is a throwable.
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int $options
     *   Integer: maximum depth.
     *   Ignored if not array|int.
     *
     * @return Inspector
     *      Stringable. Chainable.
     */
    public function variable(mixed $subject, array|int $options = []): InspectorInterface
    {
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $subject,
            $options,
            $subject instanceof \Throwable
        );
    }

    /**
     * Trace exception or do back-trace.
     *
     * @see Inspector::$options
     *
     * @param \Throwable|null $throwableOrNull
     *      Null: do back-trace.
     * @param array|int $options
     *   Integer: maximum depth.
     *   Ignored if not array|int.
     *
     * @return Inspector
     *      Stringable. Chainable.
     */
    public function trace(?\Throwable $throwableOrNull, array|int $options = []): InspectorInterface
    {
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $throwableOrNull,
            $options,
            true
        );
    }

    /**
     * Get configuration object.
     *
     * @param object|null $config
     *      Ignored if internal configuration object already set.
     *
     * @return object
     */
    public function getConfig(?object $config = null): object {
        if (!$this->config) {
            $class_config = static::CLASS_CONFIG;
            // Pass arg $config to new Config instance,
            // unless $config already is a Config.
            $this->config = $config && is_a($config, $class_config) ? $config :
                new $class_config($config);
        }
        return $this->config;
    }

    /**
     * Get unicode helper.
     *
     * @return \SimpleComplex\Inspect\Helper\UnicodeInterface
     */
    public function getUnicode(): UnicodeInterface {
        return $this->unicode;
    }

    /**
     * Root of the application or document root.
     *
     * Do override to accommodate to framework;
     * like Symfony kernel project dir or Drupal root.
     *
     * @return string
     *      Empty: root dir cannot be established.
     */
    public function rootDir(): string
    {
        if (!$this->rootDirLength) {
            $class_utils = '\\SimpleComplex\\Utils\\Utils';
            if (class_exists($class_utils)) {
                try {
                    /** @var \SimpleComplex\Utils\Utils $utils */
                    $utils = call_user_func($class_utils . '::getInstance');
                    $this->rootDir = $utils->documentRoot();
                    $this->rootDirLength = $this->unicode->strlen($this->rootDir);
                    return $this->rootDir;
                }
                catch (\Throwable $xcptn) {
                    error_log($xcptn->getMessage());
                }
            }
            // Flag that root dir cannot be established.
            $this->rootDir = '';
            $this->rootDirLength = -1;
        }
        return $this->rootDir;
    }

    /**
     * @return int
     *      Negative: root dir cannot be established.
     */
    public function rootDirLength(): int
    {
        if (!$this->rootDirLength) {
            $this->rootDir();
        }
        return $this->rootDirLength;
    }

    /**
     * Replace root dir from string.
     *
     * @see Helper\Config::$rootdir_replace
     * @see Inspector::$options['rootdir_replace']
     *
     * @param string $subject
     * @param bool $leading
     *      True: replace only if start of subject.
     *
     * @return string
     */
    public function rootDirReplace(string $subject, bool $leading = false): string {
        if (!$this->rootDirLength) {
            $this->rootDir();
        }
        if ($this->rootDirLength > 0) {
            if (!$leading) {
                return str_replace($this->rootDir, static::ROOT_DIR_SUBSTITUTE, $subject);
            }
            elseif ($this->unicode->strpos($subject, $this->rootDir) === 0) {
                return static::ROOT_DIR_SUBSTITUTE . substr($subject, $this->rootDirLength);
            }
        }
        return $subject;
    }
}
