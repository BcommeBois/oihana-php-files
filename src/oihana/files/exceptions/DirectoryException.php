<?php

namespace oihana\files\exceptions ;

use Exception;

use oihana\exceptions\ExceptionTrait;

/**
 * Thrown when an error occurred in the component Directory.
 */
class DirectoryException extends Exception
{
    use ExceptionTrait ;
}