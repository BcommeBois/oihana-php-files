<?php

namespace oihana\files\exceptions ;

use Exception;
use oihana\exceptions\ExceptionTrait;

/**
 * Thrown when an error occurred in the component File.
 */
class FileException extends Exception
{
    use ExceptionTrait ;
}