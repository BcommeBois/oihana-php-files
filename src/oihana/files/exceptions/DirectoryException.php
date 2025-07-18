<?php

namespace oihana\files\exceptions ;

use Exception;

use oihana\exceptions\ExceptionTrait;

/**
 * Thrown when an error occurred in the component Directory.
 *
 * @package oihana\files\exceptions
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class DirectoryException extends Exception
{
    use ExceptionTrait ;
}