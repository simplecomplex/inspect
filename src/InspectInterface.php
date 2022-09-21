<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;

use SimpleComplex\Inspect\Helper\UnicodeInterface;

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
    public function inspect($subject, $options = []): InspectorInterface;

    /**
     * Inspect variable.
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return InspectorInterface
     */
    public function variable($subject, $options = []): InspectorInterface;

    /**
     * Trace exception or do back-trace.
     *
     * @param \Throwable|null $throwableOrNull
     * @param array $options
     *
     * @return InspectorInterface
     */
    public function trace(?\Throwable $throwableOrNull, $options = []): InspectorInterface;

    /**
     * Get configuration object.
     *
     * @param object|null $config
     *
     * @return object
     */
    public function getConfig(?object $config = null): object;

    /**
     * Get unicode helper.
     *
     * @return \SimpleComplex\Inspect\Helper\UnicodeInterface
     */
    public function getUnicode(): UnicodeInterface;

  /**
   * Root of the application or document root.
   *
   * Do override to accommodate to framework;
   * like Symfony kernel project dir or Drupal root.
   *
   * @return string
   *      Empty: root dir cannot be established.
   */
  public function rootDir(): string;

    /**
     * @return int
     *      Negative: root dir cannot be established.
     */
    public function rootDirLength(): int;

    /**
     * Replace root dir from string.
     *
     * @param string $subject
     * @param bool $leading
     *      True: replace only if start of subject.
     *
     * @return string
     */
    public function rootDirReplace(string $subject, bool $leading = false): string;
}
