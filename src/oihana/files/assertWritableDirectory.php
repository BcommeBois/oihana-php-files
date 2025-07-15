<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

/**
 * Asserts that a directory exists and is readable and writable.
 * @param string|null $directory The path of the directory to check.
 * @return void
 * @throws DirectoryException If the directory path is null, empty, or if the directory does not exist or is not accessible.
 */
function assertWritableDirectory( ?string $directory ): void
{
    assertDirectory( $directory , isWritable : true  ) ;
}