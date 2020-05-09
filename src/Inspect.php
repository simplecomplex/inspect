<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Inspect\Helper\Config;
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
     * @var Inspect
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * Don't use method if there's a dependency injection container available.
     *
     * Passing overriding config vars is not possible.
     *
     * @return Inspect
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = (new static())->configure();
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
     * @var \SimpleComplex\Inspect\Helper\Config
     */
    public $config;

    /**
     * @var \SimpleComplex\Inspect\Helper\Unicode
     */
    public $unicode;

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * Values:
     * - zero: root dir definition not attempted yet
     * - negative: root dir cannot be established
     *
     * @var int
     */
    protected $rootDirLength = 0;

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
     *
    public function __construct(ConfigFactoryInterface $config_factory)
    {
        parent::__construct();

        $this->configure(
            $config_factory->get('inspect.settings')
        );
        // OR, do what configure() essentially does.
        $this->config = new \Drupal\inspect\Helper\Config(
            $config_factory->get('inspect.settings')
        );
    }
    */

    /**
     * This method must be called before use of the instance.
     *
     * Otherwise similar work must be done in overriding constructor
     * or other method.
     *
     * @param object|null $config
     *      Null if no custom value(s), overriding Inspecter defaults.
     *
     * @return Inspect
     */
    public function configure(?object $config = null) : self
    {
        if (!$this->config) {
            $class_config = static::CLASS_CONFIG;
            // Pass arg $config to new Config instance,
            // unless $config already is a Config.
            $this->config = $config && is_a($config, $class_config) ? $config :
                new $class_config($config);
        }
        return $this;
    }

    /**
     * Do variable inspection, unless arg $subject is a throwable; then trace.
     *
     * Back-tracing (without Throwable) can also be accomplished by passing
     * 'trace':true option.
     *
     * @see Inspector::$options
     *
     * @param mixed $subject
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function inspect($subject, $options = []) : InspectorInterface
    {
        if (!$this->config) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
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
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function variable($subject, $options = []) : InspectorInterface
    {
        if (!$this->config) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
        $options['kind'] = 'variable';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $subject,
            $options
        );
    }

    /**
     * Trace exception or do back-trace.
     *
     * @see Inspector::$options
     *
     * @param \Throwable|null $throwableOrNull
     *      Null: do back-trace.
     * @param array|int|string $options
     *
     * @return Inspector
     *      Stringable.
     *
     * @throws \LogicException
     *      Instance not configured.
     */
    public function trace(/*?\Throwable*/ $throwableOrNull, $options = []) : InspectorInterface
    {
        if (!$this->config) {
            throw new \LogicException(get_class($this) . ' is not configured.');
        }
        $options['kind'] = 'trace';
        $class_inspector = static::CLASS_INSPECTOR;
        /** @var Inspector */
        return new $class_inspector(
            $this,
            $throwableOrNull,
            $options
        );
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
    public function rootDir() : string
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
    public function rootDirLength() : int
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
    public function rootDirReplace(string $subject, bool $leading = false) : string {
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
