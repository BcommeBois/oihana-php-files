<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

/**
 * Asserts that a directory exists and is readable and writable.
 *
 * @param string|null $directory The path of the directory to check.
 *
 * @return void
 *
 * @throws DirectoryException If the directory path is null, empty, or if the directory does not exist or is not accessible.
 *
 * @example
 *
 * **Success case:** Check a writable directory.
 * The system's temporary directory is a good candidate.
 * ```php
 * $tempDir = sys_get_temp_dir();
 * try
 * {
 *     assertWritableDirectory($tempDir);
 *    // Script continues if the directory is indeed writable.
 *    echo "Directory $tempDir is writable.";
 * }
 * catch (DirectoryException $e)
 * {
 *     // Handle the error if the directory is not accessible.
 * }
 * ```
 * **Failure case:** The directory does not exist.
 * ```php
 * $fakeDir = '/a/path/that/does/not/exist';
 * try
 * {
 *     assertWritableDirectory($fakeDir);
 * }
 * catch (DirectoryException $e)
 * {
 *      // An exception is thrown because the directory is not valid.
 *      echo "Caught expected exception: " . $e->getMessage();
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function assertWritableDirectory( ?string $directory ): void
{
    assertDirectory( $directory , isWritable : true  ) ;
}