<?php
/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Inspect;


interface InspectorInterface
{
    /**
     * @param bool $noPreface
     *
     * @return string
     */
    public function toString($noPreface = false) : string;

    /**
     * @return string
     */
    public function __toString() : string;

    /**
     * List of inspection properties.
     *
     * @return array
     */
    public function get() : array;

    /**
     * Generated output exceeds limit?
     *
     * @return bool
     */
    public function exceedsLength() : bool;

    /**
     * Execution closing in to timeout?
     *
     * @return bool
     */
    public function exceedsTime() : bool;
}
